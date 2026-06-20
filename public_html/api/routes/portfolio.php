<?php
declare(strict_types=1);
/**
 * /api/portfolio        GET positions + analytics, POST open
 * /api/portfolio/{id}   PUT close/update, DELETE
 */

$user = Auth::require();
$db   = Database::instance();
$method = $ctx['method'];
$action = $ctx['action'];
$body   = $ctx['body'];

if ($method === 'GET') {
    $positions = $db->fetchAll('SELECT * FROM portfolio WHERE user_id = ? ORDER BY opened_at DESC', [$user['id']]);
    // refresh current prices + unrealised pnl for open crypto positions
    foreach ($positions as &$p) {
        if ($p['status'] === 'open') {
            $px = MarketData::lastPrice($p['symbol']);
            if ($px !== null) { $p['current_price'] = $px; }
            $cur = (float) ($p['current_price'] ?: $p['avg_entry']);
            $dir = $p['direction'] === 'long' ? 1 : -1;
            $p['unrealized_pnl'] = round(($cur - (float) $p['avg_entry']) * (float) $p['quantity'] * $dir, 8);
        } else {
            $p['unrealized_pnl'] = 0;
        }
    }
    unset($p);
    Response::json(['positions' => $positions, 'analytics' => portfolio_analytics($positions)]);
}

if ($method === 'POST') {
    $symbol = strtoupper(preg_replace('/[^A-Z0-9=\.\-]/i', '', (string) ($body['symbol'] ?? '')));
    if ($symbol === '') { Response::error('Symbol required', 422); }
    $id = $db->insert('portfolio', [
        'user_id'   => $user['id'],
        'symbol'    => $symbol,
        'direction' => ($body['direction'] ?? 'long') === 'short' ? 'short' : 'long',
        'quantity'  => (float) ($body['quantity'] ?? 0),
        'avg_entry' => (float) ($body['avg_entry'] ?? 0),
        'current_price' => MarketData::lastPrice($symbol),
        'status'    => 'open',
    ]);
    Response::json(['id' => $id], 201);
}

if ($method === 'PUT' && $action) {
    $pos = $db->fetch('SELECT * FROM portfolio WHERE id = ? AND user_id = ?', [(int) $action, $user['id']]);
    if (!$pos) { Response::error('Position not found', 404); }
    if (!empty($body['close'])) {
        $exit = (float) ($body['exit_price'] ?? $pos['current_price'] ?? $pos['avg_entry']);
        $dir = $pos['direction'] === 'long' ? 1 : -1;
        $realized = round(($exit - (float) $pos['avg_entry']) * (float) $pos['quantity'] * $dir, 8);
        $db->update('portfolio', [
            'status' => 'closed', 'current_price' => $exit,
            'realized_pnl' => $realized, 'closed_at' => date('Y-m-d H:i:s'),
        ], ['id' => (int) $action]);
        Response::json(['message' => 'Position closed', 'realized_pnl' => $realized]);
    }
    $db->update('portfolio', [
        'quantity'  => (float) ($body['quantity'] ?? $pos['quantity']),
        'avg_entry' => (float) ($body['avg_entry'] ?? $pos['avg_entry']),
    ], ['id' => (int) $action]);
    Response::json(['message' => 'Updated']);
}

if ($method === 'DELETE' && $action) {
    $db->run('DELETE FROM portfolio WHERE id = ? AND user_id = ?', [(int) $action, $user['id']]);
    Response::json(['message' => 'Deleted']);
}

Response::error('Invalid portfolio operation', 400);

function portfolio_analytics(array $positions): array
{
    $open = 0; $closed = 0; $totalProfit = 0; $totalLoss = 0; $wins = 0; $closedCount = 0;
    $equityCurve = []; $running = 0;
    foreach (array_reverse($positions) as $p) {
        if ($p['status'] === 'open') {
            $open++;
            $running += (float) ($p['unrealized_pnl'] ?? 0);
        } else {
            $closed++; $closedCount++;
            $r = (float) $p['realized_pnl'];
            $running += $r;
            if ($r >= 0) { $totalProfit += $r; if ($r > 0) { $wins++; } } else { $totalLoss += abs($r); }
        }
        $equityCurve[] = round($running, 2);
    }
    // max drawdown
    $peak = 0; $maxDd = 0;
    foreach ($equityCurve as $v) {
        $peak = max($peak, $v);
        $maxDd = max($maxDd, $peak - $v);
    }
    return [
        'open_trades'   => $open,
        'closed_trades' => $closed,
        'total_profit'  => round($totalProfit, 2),
        'total_loss'    => round($totalLoss, 2),
        'net_pnl'       => round($totalProfit - $totalLoss, 2),
        'win_rate'      => $closedCount ? round($wins / $closedCount * 100, 2) : 0,
        'max_drawdown'  => round($maxDd, 2),
        'equity_curve'  => $equityCurve,
    ];
}
