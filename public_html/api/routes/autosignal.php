<?php
declare(strict_types=1);
/**
 * /api/autosignal  (POST) - Automatic "submit a chart, get a signal" engine.
 *
 * Accepts (multipart/form-data or JSON):
 *   - chart      : optional uploaded chart image (read with rule-based GD pixel analysis)
 *   - symbol     : optional; if omitted, parsed from the uploaded filename
 *   - timeframe  : 1m|5m|15m|1h|4h|1d (default 1h)
 *   - market     : crypto|stocks|forex|commodities (default crypto)
 *   - style      : scalping|intraday|swing (default intraday)
 *
 * Pipeline: chart pixel-read (optional) + LIVE OHLCV -> indicators, structure,
 * supply/demand, SMC -> SignalEngine::instant() -> always returns a signal.
 * 100% rule-based mathematics. No AI.
 */

$user = Auth::require();
if ($ctx['method'] !== 'POST') { Response::error('POST required', 405); }
if (!Security::rateLimit('autosignal:' . $user['id'], 30, 60)) {
    Response::error('Too many auto-signal requests. Please wait a moment.', 429);
}

$body = $ctx['body'];

// 1) Optional chart upload + rule-based read
$chartRead = null;
$chartPath = null;
$symbol = strtoupper(preg_replace('/[^A-Z0-9=\.\-]/i', '', (string) ($body['symbol'] ?? ($_POST['symbol'] ?? ''))));

if (!empty($_FILES['chart']) && ($_FILES['chart']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $origName = (string) ($_FILES['chart']['name'] ?? '');
    if ($symbol === '') {
        $guess = ChartReader::symbolFromFilename($origName);
        if ($guess) { $symbol = $guess; }
    }
    // read pixels BEFORE moving (analyze tmp file)
    $chartRead = ChartReader::analyze($_FILES['chart']['tmp_name']);
    try {
        $chartPath = Security::handleImageUpload($_FILES['chart'], 'charts', (int) $user['id']);
    } catch (Throwable $e) {
        // non-fatal: keep the read result even if storage fails
    }
}

if ($symbol === '') {
    Response::error('A symbol is required. Provide ?symbol= or upload a chart whose filename contains the pair (e.g. BTCUSDT).', 422);
}

$tfReq     = $body['timeframe'] ?? '1h';
$timeframe = in_array($tfReq, ['1m','5m','15m','1h','4h','1d'], true) ? $tfReq : '1h';
$mktReq    = $body['market'] ?? 'crypto';
$market    = in_array($mktReq, ['crypto','stocks','forex','commodities'], true) ? $mktReq : 'crypto';
$styleReq  = $body['style'] ?? 'intraday';
$style     = in_array($styleReq, ['scalping','intraday','swing'], true) ? $styleReq : 'intraday';

// 2) Fetch live OHLCV
if ($market === 'crypto') {
    $ohlcv = MarketData::klines($symbol, $timeframe, 250, true);
    if (count($ohlcv['close']) < 60) {
        $ohlcv = MarketData::klines($symbol, $timeframe, 250, false);
    }
} else {
    $yfInterval = match ($timeframe) { '1m'=>'1m','5m'=>'5m','15m'=>'15m','1h'=>'60m','4h'=>'60m', default=>'1d' };
    $range = in_array($yfInterval, ['1m','5m','15m'], true) ? '5d' : '6mo';
    $ohlcv = MarketData::yahooChart($symbol, $range, $yfInterval);
}

if (count($ohlcv['close']) < 50) {
    // If we at least read the chart, return that with guidance
    if ($chartRead && ($chartRead['ok'] ?? false)) {
        Response::json([
            'symbol'       => $symbol, 'timeframe' => $timeframe, 'market' => $market,
            'chart_read'   => $chartRead, 'chart_path' => $chartPath, 'signal' => null,
            'message'      => 'Chart read succeeded but live data for this symbol was unavailable from the server (it may be geo-restricted). Signal needs live OHLCV.',
        ]);
    }
    Response::error('Insufficient live market data for ' . $symbol . ' (' . $timeframe . '). The data source may be unreachable from this server.', 502);
}

// 3) Full rule-based analysis
$snapshot  = Indicators::snapshot($ohlcv);
$summary   = Indicators::summary($snapshot);
$structure = MarketStructure::analyze($ohlcv['high'], $ohlcv['low'], $ohlcv['close']);
$zones     = SupplyDemand::detect($ohlcv['high'], $ohlcv['low'], $ohlcv['close'], $ohlcv['open']);
$smc       = SMC::analyze($ohlcv);

$analysis = [
    'symbol' => $symbol, 'timeframe' => $timeframe,
    'snapshot' => $snapshot, 'structure' => $structure, 'zones' => $zones, 'smc' => $smc,
];

// 4) Instant signal (always returns) + all-style variants
$signal = SignalEngine::instant($analysis, $style, $chartRead);
$variants = [];
foreach (['scalping','intraday','swing'] as $st) {
    $variants[$st] = SignalEngine::instant($analysis, $st, $chartRead);
}

// 5) Persist the primary signal so it appears in the user's signal history
try {
    $sigId = SignalEngine::persist($signal, $signal['confidence'] >= 80);
    $signal['id'] = $sigId;
} catch (Throwable) {}

// 6) Cross-check note: does the chart read agree with the data-driven signal?
$agreement = null;
if ($chartRead && ($chartRead['ok'] ?? false)) {
    $chartDir = $chartRead['bias'] === 'bullish' ? 'long' : ($chartRead['bias'] === 'bearish' ? 'short' : 'neutral');
    $agreement = ($chartDir === 'neutral') ? 'inconclusive' : ($chartDir === $signal['direction'] ? 'agrees' : 'diverges');
}

Helpers::log((int) $user['id'], 'auto_signal', 'signals', ['symbol' => $symbol, 'tf' => $timeframe, 'dir' => $signal['direction']]);

Response::json([
    'symbol'      => $symbol,
    'timeframe'   => $timeframe,
    'market'      => $market,
    'price'       => $snapshot['price'] ?? null,
    'chart_read'  => $chartRead,
    'chart_path'  => $chartPath,
    'chart_agreement' => $agreement,
    'summary'     => $summary,
    'structure'   => ['trend' => $structure['trend'], 'trend_score' => $structure['trend_score'], 'counts' => $structure['counts']],
    'zones'       => array_slice($zones, 0, 5),
    'smc'         => ['bos_choch' => $smc['bos_choch'] ?? [], 'premium_discount' => $smc['premium_discount'] ?? null, 'fvg' => $smc['fvg'] ?? []],
    'signal'      => $signal,
    'variants'    => $variants,
]);
