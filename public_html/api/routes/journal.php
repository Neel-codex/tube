<?php
declare(strict_types=1);
/**
 * /api/journal           GET list + stats, POST create
 * /api/journal/{id}      PUT update / close, DELETE
 * /api/journal/upload    POST screenshot (multipart)
 */

$user = Auth::require();
$db   = Database::instance();
$method = $ctx['method'];
$action = $ctx['action'];
$body   = $ctx['body'];

if ($action === 'upload' && $method === 'POST') {
    if (empty($_FILES['screenshot'])) { Response::error('No file provided', 422); }
    try {
        $path = Security::handleImageUpload($_FILES['screenshot'], 'charts', (int) $user['id']);
    } catch (Throwable $e) {
        Response::error($e->getMessage(), 422);
    }
    Response::json(['path' => $path], 201);
}

if ($method === 'GET') {
    $rows = $db->fetchAll('SELECT * FROM trade_journal WHERE user_id = ? ORDER BY created_at DESC LIMIT 500', [$user['id']]);
    Response::json(['trades' => $rows, 'stats' => journal_stats($db, (int) $user['id'])]);
}

if ($method === 'POST') {
    $symbol = strtoupper(preg_replace('/[^A-Z0-9=\.\-]/i', '', (string) ($body['symbol'] ?? '')));
    if ($symbol === '') { Response::error('Symbol required', 422); }
    $direction = ($body['direction'] ?? 'long') === 'short' ? 'short' : 'long';
    $entry = (float) ($body['entry_price'] ?? 0);
    $exit  = isset($body['exit_price']) ? (float) $body['exit_price'] : null;
    $size  = (float) ($body['position_size'] ?? 0);
    [$pnl, $pnlPct, $rr, $outcome] = journal_compute($direction, $entry, $exit,
        isset($body['stop_loss']) ? (float) $body['stop_loss'] : null,
        isset($body['take_profit']) ? (float) $body['take_profit'] : null, $size);
    $id = $db->insert('trade_journal', [
        'user_id'       => $user['id'],
        'symbol'        => $symbol,
        'direction'     => $direction,
        'entry_price'   => $entry,
        'exit_price'    => $exit,
        'stop_loss'     => isset($body['stop_loss']) ? (float) $body['stop_loss'] : null,
        'take_profit'   => isset($body['take_profit']) ? (float) $body['take_profit'] : null,
        'position_size' => $size,
        'pnl'           => $pnl,
        'pnl_pct'       => $pnlPct,
        'rr'            => $rr,
        'outcome'       => $outcome,
        'screenshot'    => isset($body['screenshot']) ? substr((string) $body['screenshot'], 0, 255) : null,
        'notes'         => isset($body['notes']) ? substr((string) $body['notes'], 0, 5000) : null,
        'opened_at'     => $body['opened_at'] ?? date('Y-m-d H:i:s'),
        'closed_at'     => $exit !== null ? ($body['closed_at'] ?? date('Y-m-d H:i:s')) : null,
    ]);
    Response::json(['id' => $id, 'pnl' => $pnl, 'rr' => $rr, 'outcome' => $outcome], 201);
}

if ($method === 'PUT' && $action) {
    $trade = $db->fetch('SELECT * FROM trade_journal WHERE id = ? AND user_id = ?', [(int) $action, $user['id']]);
    if (!$trade) { Response::error('Trade not found', 404); }
    $exit = isset($body['exit_price']) ? (float) $body['exit_price'] : ($trade['exit_price'] !== null ? (float) $trade['exit_price'] : null);
    [$pnl, $pnlPct, $rr, $outcome] = journal_compute($trade['direction'], (float) $trade['entry_price'], $exit,
        $trade['stop_loss'] !== null ? (float) $trade['stop_loss'] : null,
        $trade['take_profit'] !== null ? (float) $trade['take_profit'] : null, (float) $trade['position_size']);
    $db->update('trade_journal', [
        'exit_price' => $exit, 'pnl' => $pnl, 'pnl_pct' => $pnlPct, 'rr' => $rr,
        'outcome' => $outcome, 'closed_at' => $exit !== null ? date('Y-m-d H:i:s') : null,
        'notes' => isset($body['notes']) ? substr((string) $body['notes'], 0, 5000) : $trade['notes'],
    ], ['id' => (int) $action]);
    Response::json(['message' => 'Updated', 'pnl' => $pnl, 'outcome' => $outcome]);
}

if ($method === 'DELETE' && $action) {
    $db->run('DELETE FROM trade_journal WHERE id = ? AND user_id = ?', [(int) $action, $user['id']]);
    Response::json(['message' => 'Trade deleted']);
}

Response::error('Invalid journal operation', 400);

// ---- helpers ----
function journal_compute(string $dir, float $entry, ?float $exit, ?float $sl, ?float $tp, float $size): array
{
    if ($exit === null || $entry <= 0) {
        return [null, null, null, 'open'];
    }
    $diff = $dir === 'long' ? ($exit - $entry) : ($entry - $exit);
    $pnl = $diff * ($size > 0 ? $size : 1);
    $pnlPct = round(($diff / $entry) * 100, 4);
    $rr = null;
    if ($sl !== null) {
        $risk = abs($entry - $sl);
        if ($risk > 0) { $rr = round(abs($diff) / $risk * ($diff >= 0 ? 1 : -1), 2); }
    }
    $outcome = $diff > 0 ? 'win' : ($diff < 0 ? 'loss' : 'breakeven');
    return [round($pnl, 8), $pnlPct, $rr, $outcome];
}

function journal_stats(Database $db, int $userId): array
{
    $rows = $db->fetchAll('SELECT pnl, rr, outcome FROM trade_journal WHERE user_id = ? AND outcome <> "open"', [$userId]);
    $total = count($rows);
    if ($total === 0) {
        return ['total_trades' => 0, 'win_rate' => 0, 'avg_rr' => 0, 'profit_factor' => 0, 'total_pnl' => 0];
    }
    $wins = 0; $grossProfit = 0; $grossLoss = 0; $rrSum = 0; $rrCount = 0; $totalPnl = 0;
    foreach ($rows as $r) {
        $pnl = (float) $r['pnl'];
        $totalPnl += $pnl;
        if ($r['outcome'] === 'win') { $wins++; }
        if ($pnl >= 0) { $grossProfit += $pnl; } else { $grossLoss += abs($pnl); }
        if ($r['rr'] !== null) { $rrSum += (float) $r['rr']; $rrCount++; }
    }
    return [
        'total_trades'  => $total,
        'win_rate'      => round($wins / $total * 100, 2),
        'avg_rr'        => $rrCount ? round($rrSum / $rrCount, 2) : 0,
        'profit_factor' => $grossLoss > 0 ? round($grossProfit / $grossLoss, 2) : ($grossProfit > 0 ? 99.99 : 0),
        'total_pnl'     => round($totalPnl, 2),
    ];
}
