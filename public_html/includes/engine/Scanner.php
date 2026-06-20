<?php
declare(strict_types=1);

/**
 * Scanner - real-time market scanner over Binance Futures pairs.
 *
 * For each symbol/timeframe it computes the full analysis stack and:
 *   - detects setups (breakout, reversal, pullback, range break, volume
 *     surge, volatility expansion, liquidity sweep, trend continuation)
 *   - assigns a rating (strong_buy..strong_sell)
 *   - persists to scanner_results (upsert per symbol+timeframe)
 */
final class Scanner
{
    /** Run the scanner for one timeframe across the top symbols. */
    public static function run(string $timeframe = '15m', ?int $limit = null): array
    {
        $limit = $limit ?? (int) Helpers::setting('scanner_symbols_limit', 120);
        $minVol = (float) Helpers::setting('scanner_min_volume_usdt', 5_000_000);
        $symbols = MarketData::topFuturesSymbols($limit, $minVol);
        $results = [];
        foreach ($symbols as $symbol) {
            try {
                $res = self::analyzeSymbol($symbol, $timeframe);
                if ($res !== null) {
                    self::persist($res);
                    $results[] = $res;
                }
            } catch (Throwable $e) {
                error_log("Scanner error for $symbol: " . $e->getMessage());
            }
        }
        return $results;
    }

    /** Full analysis for a single symbol+timeframe. Returns null if no data. */
    public static function analyzeSymbol(string $symbol, string $timeframe): ?array
    {
        $ohlcv = MarketData::klines($symbol, $timeframe, 250, true);
        if (count($ohlcv['close']) < 60) {
            return null;
        }
        $snap   = Indicators::snapshot($ohlcv);
        if (empty($snap['ready'])) {
            return null;
        }
        $struct = MarketStructure::analyze($ohlcv['high'], $ohlcv['low'], $ohlcv['close']);
        $zones  = SupplyDemand::detect($ohlcv['high'], $ohlcv['low'], $ohlcv['close'], $ohlcv['open']);
        $smc    = SMC::analyze($ohlcv);

        $setups = self::detectSetups($ohlcv, $snap, $struct, $smc);
        $rating = self::rate($snap, $struct, $smc);

        $price  = $snap['price'];
        $change = self::pctChange($ohlcv['close']);

        return [
            'symbol'      => $symbol,
            'timeframe'   => $timeframe,
            'price'       => $price,
            'change_pct'  => $change,
            'volume'      => $snap['volume'],
            'setup_type'  => $setups[0]['type'] ?? 'none',
            'rating'      => $rating['rating'],
            'rating_score'=> $rating['score'],
            'trend'       => $struct['trend'],
            'trend_score' => $struct['trend_score'],
            'rsi'         => $snap['rsi'],
            'atr'         => $snap['atr'],
            'setups'      => $setups,
            'snapshot'    => $snap,
            'structure'   => $struct,
            'zones'       => $zones,
            'smc'         => $smc,
        ];
    }

    /** Detect the catalogue of setups. */
    private static function detectSetups(array $ohlcv, array $s, array $struct, array $smc): array
    {
        $c = $ohlcv['close']; $h = $ohlcv['high']; $l = $ohlcv['low']; $v = $ohlcv['volume'];
        $n = count($c);
        $setups = [];
        $price = $s['price'];

        // Volume surge: last volume vs 20-bar average
        $volAvg = array_sum(array_slice($v, -21, 20)) / 20;
        $volRatio = $volAvg > 0 ? end($v) / $volAvg : 0;
        if ($volRatio >= 2.0) {
            $setups[] = ['type' => 'volume_surge', 'detail' => 'Volume ' . round($volRatio, 1) . 'x average'];
        }

        // Volatility expansion: ATR rising vs prior ATR
        $atrSeries = Indicators::atr($h, $l, $c, 14);
        $atrNow = Indicators::last($atrSeries);
        $atrPrev = $atrSeries[$n - 6] ?? null;
        if ($atrNow && $atrPrev && $atrNow > $atrPrev * 1.3) {
            $setups[] = ['type' => 'volatility_expansion', 'detail' => 'ATR expanding'];
        }

        // Breakout: price closes above last swing high / range high
        $rangeHigh = max(array_slice($h, -21, 20));
        $rangeLow  = min(array_slice($l, -21, 20));
        if ($price > $rangeHigh) {
            $setups[] = ['type' => 'breakout', 'detail' => 'Closed above 20-bar high'];
        } elseif ($price < $rangeLow) {
            $setups[] = ['type' => 'range_break', 'detail' => 'Closed below 20-bar low'];
        }

        // Trend continuation: aligned EMAs + trend
        if ($struct['trend'] === 'uptrend' && $s['ema20'] && $s['ema50'] && $s['ema20'] > $s['ema50'] && $price > $s['ema20']) {
            $setups[] = ['type' => 'trend_continuation', 'detail' => 'Bullish EMA stack'];
        }
        if ($struct['trend'] === 'downtrend' && $s['ema20'] && $s['ema50'] && $s['ema20'] < $s['ema50'] && $price < $s['ema20']) {
            $setups[] = ['type' => 'trend_continuation', 'detail' => 'Bearish EMA stack'];
        }

        // Pullback: in uptrend, price dips to EMA20/50 with RSI cooling
        if ($struct['trend'] === 'uptrend' && $s['ema50'] && $price <= $s['ema20'] && $price >= $s['ema50'] && $s['rsi'] < 50) {
            $setups[] = ['type' => 'pullback', 'detail' => 'Retrace to moving averages'];
        }

        // Reversal: oversold/overbought + structure shift (CHOCH)
        $choch = array_filter($smc['bos_choch'] ?? [], static fn ($e) => $e['type'] === 'CHOCH');
        if (!empty($choch)) {
            $setups[] = ['type' => 'reversal', 'detail' => 'CHOCH structure shift'];
        } elseif ($s['rsi'] !== null && ($s['rsi'] < 25 || $s['rsi'] > 75)) {
            $setups[] = ['type' => 'reversal', 'detail' => 'RSI extreme ' . round($s['rsi'], 1)];
        }

        // Liquidity sweep
        if (!empty($smc['liquidity_sweeps'])) {
            $setups[] = ['type' => 'liquidity_sweep', 'detail' => $smc['liquidity_sweeps'][0]['type']];
        }

        return $setups;
    }

    /** Rate the symbol on the strong_buy..strong_sell scale. */
    private static function rate(array $s, array $struct, array $smc): array
    {
        $score = 0.0;

        // Trend
        $score += $struct['trend_score'] * 0.4;          // [-40,40]

        // Momentum (RSI relative to 50)
        if ($s['rsi'] !== null) {
            $score += (($s['rsi'] - 50) / 50) * 15;       // [-15,15]
        }
        // MACD
        if ($s['macd_hist'] !== null) {
            $score += $s['macd_hist'] > 0 ? 8 : -8;
        }
        // EMA alignment
        if ($s['ema50'] && $s['ema200']) {
            $score += $s['ema50'] > $s['ema200'] ? 10 : -10;
        }
        // Price vs VWAP
        if ($s['vwap'] && $s['price']) {
            $score += $s['price'] > $s['vwap'] ? 6 : -6;
        }
        // SMC bias
        foreach ($smc['bos_choch'] ?? [] as $e) {
            $score += $e['direction'] === 'bullish' ? 6 : -6;
        }
        // Premium/discount (buy in discount, sell in premium)
        if (($smc['premium_discount']['zone'] ?? '') === 'discount') { $score += 4; }
        elseif (($smc['premium_discount']['zone'] ?? '') === 'premium') { $score -= 4; }

        $score = max(-100, min(100, $score));
        $rating = match (true) {
            $score >= 45  => 'strong_buy',
            $score >= 15  => 'buy',
            $score <= -45 => 'strong_sell',
            $score <= -15 => 'sell',
            default       => 'neutral',
        };
        return ['rating' => $rating, 'score' => round($score, 2)];
    }

    private static function pctChange(array $close): float
    {
        $n = count($close);
        if ($n < 2) { return 0.0; }
        $prev = $close[$n - 2];
        return $prev > 0 ? round((($close[$n - 1] - $prev) / $prev) * 100, 4) : 0.0;
    }

    /** Upsert a scanner result row. */
    private static function persist(array $r): void
    {
        $db = Database::instance();
        $db->run(
            'INSERT INTO scanner_results
               (symbol,timeframe,price,change_pct,volume,setup_type,rating,trend_score,rsi,atr,signals,scanned_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE
               price=VALUES(price), change_pct=VALUES(change_pct), volume=VALUES(volume),
               setup_type=VALUES(setup_type), rating=VALUES(rating), trend_score=VALUES(trend_score),
               rsi=VALUES(rsi), atr=VALUES(atr), signals=VALUES(signals), scanned_at=NOW()',
            [
                $r['symbol'], $r['timeframe'], $r['price'], $r['change_pct'], $r['volume'],
                $r['setup_type'], $r['rating'], $r['trend_score'], $r['rsi'], $r['atr'],
                json_encode($r['setups']),
            ]
        );
        // Persist top zones to history
        foreach (array_slice($r['zones'], 0, 4) as $z) {
            try {
                $db->insert('zones', [
                    'symbol'     => $r['symbol'],
                    'timeframe'  => $r['timeframe'],
                    'zone_type'  => $z['type'],
                    'price_high' => $z['high'],
                    'price_low'  => $z['low'],
                    'strength'   => $z['strength'],
                    'status'     => $z['status'],
                    'meta'       => json_encode(['distance_pct' => $z['distance_pct'] ?? null]),
                ]);
            } catch (Throwable) {}
        }
    }
}
