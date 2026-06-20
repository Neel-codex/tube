<?php
declare(strict_types=1);

/**
 * MarketData - centralised market data service.
 *
 * Aggregates free public data sources:
 *   - Binance Futures (klines, tickers)  -> primary for crypto scanning
 *   - Binance Spot (fallback)
 *   - CoinGecko (global metrics, coin meta)
 *   - Yahoo Finance (stocks / forex / commodities)
 *
 * All responses are cached (DB-backed market_cache + file fallback) to
 * respect rate limits and keep the scanner fast.
 */
final class MarketData
{
    /** Perform a GET request with curl and sane defaults. */
    private static function http(string $url, int $timeout = 12): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'TradeVisionPro/1.0 (+market-data-service)',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($body === false || $code >= 400) {
            error_log("MarketData HTTP error ($code) for $url: $err");
            return null;
        }
        return is_string($body) ? $body : null;
    }

    /** Cached JSON GET. */
    private static function cachedJson(string $url, int $ttl, string $keyPrefix): ?array
    {
        $key = $keyPrefix . ':' . md5($url);
        $cached = self::cacheGet($key);
        if ($cached !== null) {
            return $cached;
        }
        $raw = self::http($url);
        if ($raw === null) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }
        self::cacheSet($key, $data, $ttl);
        return $data;
    }

    // ---------------- Cache layer ------------------------------------

    public static function cacheGet(string $key): ?array
    {
        try {
            $row = Database::instance()->fetch(
                'SELECT payload, expires_at FROM market_cache WHERE cache_key = ? LIMIT 1',
                [$key]
            );
            if ($row && (int) $row['expires_at'] > time()) {
                $d = json_decode((string) $row['payload'], true);
                return is_array($d) ? $d : null;
            }
        } catch (Throwable) {}
        // File fallback
        $file = CACHE_PATH . '/' . md5($key) . '.json';
        if (is_file($file)) {
            $d = json_decode((string) file_get_contents($file), true);
            if (is_array($d) && ($d['__exp'] ?? 0) > time()) {
                return $d['data'];
            }
        }
        return null;
    }

    public static function cacheSet(string $key, array $data, int $ttl): void
    {
        $exp = time() + $ttl;
        try {
            Database::instance()->run(
                'INSERT INTO market_cache (cache_key, payload, expires_at) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE payload = VALUES(payload), expires_at = VALUES(expires_at)',
                [$key, json_encode($data), $exp]
            );
        } catch (Throwable) {}
        $file = CACHE_PATH . '/' . md5($key) . '.json';
        @file_put_contents($file, json_encode(['__exp' => $exp, 'data' => $data]));
    }

    // ---------------- Binance Futures --------------------------------

    /**
     * All tradable USDT-perpetual futures symbols with 24h stats, sorted by volume.
     */
    public static function futuresTickers(): array
    {
        $data = self::cachedJson(BINANCE_FUTURES_API . '/fapi/v1/ticker/24hr', 60, 'fut24');
        if (!$data) {
            return [];
        }
        $out = [];
        foreach ($data as $t) {
            $sym = $t['symbol'] ?? '';
            if (!str_ends_with($sym, 'USDT')) {
                continue;
            }
            $out[] = [
                'symbol'      => $sym,
                'price'       => (float) ($t['lastPrice'] ?? 0),
                'change_pct'  => (float) ($t['priceChangePercent'] ?? 0),
                'high'        => (float) ($t['highPrice'] ?? 0),
                'low'         => (float) ($t['lowPrice'] ?? 0),
                'quote_volume'=> (float) ($t['quoteVolume'] ?? 0),
                'volume'      => (float) ($t['volume'] ?? 0),
            ];
        }
        usort($out, static fn ($a, $b) => $b['quote_volume'] <=> $a['quote_volume']);
        return $out;
    }

    /**
     * Top N liquid futures symbols above a min quote volume.
     */
    public static function topFuturesSymbols(int $limit = 120, float $minQuoteVol = 0): array
    {
        $tickers = self::futuresTickers();
        $symbols = [];
        foreach ($tickers as $t) {
            if ($t['quote_volume'] < $minQuoteVol) {
                continue;
            }
            $symbols[] = $t['symbol'];
            if (count($symbols) >= $limit) {
                break;
            }
        }
        return $symbols;
    }

    /**
     * Candlestick (kline) data from Binance Futures.
     * Returns normalised OHLCV arrays.
     *
     * @return array{open:float[],high:float[],low:float[],close:float[],volume:float[],time:int[]}
     */
    public static function klines(string $symbol, string $interval = '15m', int $limit = 200, bool $futures = true): array
    {
        $base = $futures ? BINANCE_FUTURES_API . '/fapi/v1/klines' : BINANCE_SPOT_API . '/api/v3/klines';
        $url  = sprintf('%s?symbol=%s&interval=%s&limit=%d', $base, urlencode($symbol), urlencode($interval), $limit);
        $ttl  = self::intervalTtl($interval);
        $data = self::cachedJson($url, $ttl, 'kl');
        $o = $h = $l = $c = $v = $tArr = [];
        if (!$data) {
            return ['open' => [], 'high' => [], 'low' => [], 'close' => [], 'volume' => [], 'time' => []];
        }
        foreach ($data as $k) {
            $tArr[] = (int) ($k[0] ?? 0);
            $o[] = (float) ($k[1] ?? 0);
            $h[] = (float) ($k[2] ?? 0);
            $l[] = (float) ($k[3] ?? 0);
            $c[] = (float) ($k[4] ?? 0);
            $v[] = (float) ($k[5] ?? 0);
        }
        return ['open' => $o, 'high' => $h, 'low' => $l, 'close' => $c, 'volume' => $v, 'time' => $tArr];
    }

    private static function intervalTtl(string $interval): int
    {
        return match ($interval) {
            '1m'  => 30,
            '5m'  => 60,
            '15m' => 120,
            '1h'  => 300,
            '4h'  => 600,
            '1d'  => 1800,
            default => 120,
        };
    }

    // ---------------- CoinGecko --------------------------------------

    public static function globalMetrics(): array
    {
        $data = self::cachedJson(COINGECKO_API . '/global', 300, 'cg_global');
        return $data['data'] ?? [];
    }

    public static function trendingCoins(): array
    {
        $data = self::cachedJson(COINGECKO_API . '/search/trending', 600, 'cg_trend');
        return $data['coins'] ?? [];
    }

    // ---------------- Yahoo Finance (stocks/forex/commodities) -------

    /**
     * Yahoo chart endpoint -> normalised OHLCV.
     * Symbols: AAPL, EURUSD=X, GC=F (gold), CL=F (oil), etc.
     */
    public static function yahooChart(string $symbol, string $range = '3mo', string $interval = '1d'): array
    {
        $url = sprintf(
            '%s/v8/finance/chart/%s?range=%s&interval=%s',
            YAHOO_FINANCE_API,
            urlencode($symbol),
            urlencode($range),
            urlencode($interval)
        );
        $data = self::cachedJson($url, 300, 'yf');
        $result = $data['chart']['result'][0] ?? null;
        if (!$result) {
            return ['open' => [], 'high' => [], 'low' => [], 'close' => [], 'volume' => [], 'time' => []];
        }
        $ts = $result['timestamp'] ?? [];
        $q  = $result['indicators']['quote'][0] ?? [];
        $clean = static function (array $arr): array {
            return array_values(array_filter($arr, static fn ($x) => $x !== null));
        };
        return [
            'time'   => $ts,
            'open'   => $clean($q['open'] ?? []),
            'high'   => $clean($q['high'] ?? []),
            'low'    => $clean($q['low'] ?? []),
            'close'  => $clean($q['close'] ?? []),
            'volume' => $clean($q['volume'] ?? []),
        ];
    }

    /** Latest price for any supported symbol. */
    public static function lastPrice(string $symbol, bool $futures = true): ?float
    {
        $base = $futures ? BINANCE_FUTURES_API . '/fapi/v1/ticker/price' : BINANCE_SPOT_API . '/api/v3/ticker/price';
        $data = self::cachedJson($base . '?symbol=' . urlencode($symbol), 15, 'price');
        return isset($data['price']) ? (float) $data['price'] : null;
    }
}
