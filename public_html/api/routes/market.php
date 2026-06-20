<?php
declare(strict_types=1);
/**
 * /api/market/overview   GET  global metrics + top movers + scanner summary
 * /api/market/movers     GET  top gainers / losers (futures tickers)
 */

$db = Database::instance();
$action = $ctx['action'];

if ($action === 'movers') {
    $tickers = MarketData::futuresTickers();
    usort($tickers, static fn ($a, $b) => $b['change_pct'] <=> $a['change_pct']);
    Response::json([
        'gainers' => array_slice($tickers, 0, 10),
        'losers'  => array_slice(array_reverse($tickers), 0, 10),
    ]);
}

// overview (default)
$global = MarketData::globalMetrics();
$summary = $db->fetchAll(
    "SELECT rating, COUNT(*) AS c FROM scanner_results WHERE timeframe='15m' GROUP BY rating"
);
$ratingCounts = ['strong_buy'=>0,'buy'=>0,'neutral'=>0,'sell'=>0,'strong_sell'=>0];
foreach ($summary as $row) { $ratingCounts[$row['rating']] = (int) $row['c']; }

$activeSignals = (int) $db->scalar("SELECT COUNT(*) FROM signals WHERE status='active'");
$lastScan = $db->scalar('SELECT MAX(scanned_at) FROM scanner_results');

Response::json([
    'global' => [
        'total_market_cap_usd' => $global['total_market_cap']['usd'] ?? null,
        'total_volume_usd'     => $global['total_volume']['usd'] ?? null,
        'btc_dominance'        => $global['market_cap_percentage']['btc'] ?? null,
        'active_cryptocurrencies' => $global['active_cryptocurrencies'] ?? null,
    ],
    'scanner_ratings' => $ratingCounts,
    'active_signals'  => $activeSignals,
    'last_scan'       => $lastScan,
]);
