<?php
declare(strict_types=1);
/**
 * /api/analysis/{SYMBOL}?timeframe=1h&market=crypto
 * Returns the full live technical-analysis stack for one symbol.
 */

$symbolRaw = $ctx['action'] ?: ($ctx['query']['symbol'] ?? '');
$symbol    = strtoupper(preg_replace('/[^A-Z0-9=\.\-]/i', '', $symbolRaw));
if ($symbol === '') {
    Response::error('Symbol is required, e.g. /api/analysis/BTCUSDT', 422);
}
$tfReq     = $ctx['query']['timeframe'] ?? '1h';
$timeframe = in_array($tfReq, ['1m','5m','15m','1h','4h','1d'], true) ? $tfReq : '1h';
$market = $ctx['query']['market'] ?? 'crypto';

// Fetch OHLCV from the correct source
if ($market === 'crypto') {
    $ohlcv = MarketData::klines($symbol, $timeframe, 250, true);
    if (count($ohlcv['close']) < 60) {
        $ohlcv = MarketData::klines($symbol, $timeframe, 250, false); // spot fallback
    }
} else {
    $yfInterval = match ($timeframe) { '1m'=>'1m','5m'=>'5m','15m'=>'15m','1h'=>'60m','4h'=>'60m', default=>'1d' };
    $range = in_array($yfInterval, ['1m','5m','15m'], true) ? '5d' : '6mo';
    $ohlcv = MarketData::yahooChart($symbol, $range, $yfInterval);
}

if (count($ohlcv['close']) < 50) {
    Response::error('Insufficient market data for ' . $symbol . ' (' . $timeframe . '). The data source may be unreachable from this server.', 502);
}

$snapshot  = Indicators::snapshot($ohlcv);
$summary   = Indicators::summary($snapshot);
$structure = MarketStructure::analyze($ohlcv['high'], $ohlcv['low'], $ohlcv['close']);
$zones     = SupplyDemand::detect($ohlcv['high'], $ohlcv['low'], $ohlcv['close'], $ohlcv['open']);
$smc       = SMC::analyze($ohlcv);

Response::json([
    'symbol'     => $symbol,
    'timeframe'  => $timeframe,
    'market'     => $market,
    'price'      => $snapshot['price'] ?? null,
    'indicators' => $snapshot,
    'summary'    => $summary,
    'structure'  => $structure,
    'zones'      => $zones,
    'smc'        => $smc,
    'candles'    => [
        'time'  => array_slice($ohlcv['time'], -120),
        'open'  => array_slice($ohlcv['open'], -120),
        'high'  => array_slice($ohlcv['high'], -120),
        'low'   => array_slice($ohlcv['low'], -120),
        'close' => array_slice($ohlcv['close'], -120),
        'volume'=> array_slice($ohlcv['volume'], -120),
    ],
]);
