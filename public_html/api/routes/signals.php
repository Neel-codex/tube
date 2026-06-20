<?php
declare(strict_types=1);
/** /api/signals - list trade signals with plan gating. */

$user = Auth::user();
$db   = Database::instance();
$q    = $ctx['query'];

$where = ['1=1'];
$params = [];
if (!empty($q['style']) && in_array($q['style'], ['scalping','intraday','swing'], true)) {
    $where[] = 'style = ?'; $params[] = $q['style'];
}
if (!empty($q['symbol'])) { $where[] = 'symbol = ?'; $params[] = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $q['symbol'])); }
if (!empty($q['status'])) { $where[] = 'status = ?'; $params[] = $q['status']; }

$isPro = $user && !empty($user['subscription_active']) && ($user['plan_code'] ?? 'free') !== 'free';

// Free users: only non-premium signals + daily cap
$limit = 100;
if (!$isPro) {
    $where[] = 'is_premium = 0';
    $perDay = (int) ($user['signals_per_day'] ?? 5);
    if ($perDay < 0) { $perDay = 100; }
    $limit = max(1, $perDay);
}

$sql = 'SELECT id,symbol,timeframe,style,direction,entry,stop_loss,tp1,tp2,tp3,risk_reward,confidence,confluences,status,is_premium,created_at
        FROM signals WHERE ' . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT $limit";
$rows = $db->fetchAll($sql, $params);
foreach ($rows as &$r) {
    $r['confluences'] = json_decode((string) $r['confluences'], true) ?: [];
}
unset($r);

Response::json($rows, 200, [
    'count'       => count($rows),
    'plan_locked' => !$isPro,
    'upgrade_hint'=> $isPro ? null : 'Upgrade to Pro for unlimited and premium signals.',
]);
