<?php
declare(strict_types=1);
/** /api/scanner  - list latest scanner results (with plan gating + filters). */

$user = Auth::user(); // optional; free users get limited rows
$db   = Database::instance();
$q    = $ctx['query'];

$tfReq     = $q['timeframe'] ?? '15m';
$timeframe = in_array($tfReq, ['1m','5m','15m','1h'], true) ? $tfReq : '15m';
$rating    = $q['rating'] ?? '';
$setup     = $q['setup'] ?? '';
$sortReq   = $q['sort'] ?? 'trend_score';
$sort      = in_array($sortReq, ['trend_score','change_pct','volume','rsi','symbol'], true) ? $sortReq : 'trend_score';
$dir       = (strtolower($q['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';

$where = ['timeframe = ?'];
$params = [$timeframe];
if ($rating !== '' && in_array($rating, ['strong_buy','buy','neutral','sell','strong_sell'], true)) {
    $where[] = 'rating = ?'; $params[] = $rating;
}
if ($setup !== '') { $where[] = 'setup_type = ?'; $params[] = $setup; }

// Plan gating: free / anonymous limited to 15 rows + only 15m
$isPro = $user && (($user['scanner_access'] ?? 'limited') === 'full') && !empty($user['subscription_active']);
$limit = $isPro ? 250 : 15;
if (!$isPro) {
    $where = ['timeframe = ?'];
    $params = ['15m'];
}

$sql = 'SELECT symbol,timeframe,price,change_pct,volume,setup_type,rating,trend_score,rsi,atr,signals,scanned_at
        FROM scanner_results WHERE ' . implode(' AND ', $where) .
        " ORDER BY $sort $dir LIMIT $limit";
$rows = $db->fetchAll($sql, $params);
foreach ($rows as &$r) {
    $r['signals'] = json_decode((string) $r['signals'], true) ?: [];
}
unset($r);

Response::json($rows, 200, [
    'timeframe'   => $timeframe,
    'count'       => count($rows),
    'plan_locked' => !$isPro,
    'upgrade_hint'=> $isPro ? null : 'Upgrade to Pro for full multi-timeframe scanning.',
]);
