<?php
declare(strict_types=1);
/**
 * /api/watchlists                 GET list, POST create
 * /api/watchlists/{id}            DELETE
 * /api/watchlists/items           POST add {watchlist_id,symbol}
 * /api/watchlists/items/{itemId}  DELETE
 */

$user = Auth::require();
$db   = Database::instance();
$method = $ctx['method'];
$action = $ctx['action'];
$body   = $ctx['body'];

if ($action === 'items') {
    $itemId = $ctx['id'];
    if ($method === 'POST') {
        $wlId = (int) ($body['watchlist_id'] ?? 0);
        $symbol = strtoupper(preg_replace('/[^A-Z0-9=\.\-]/i', '', (string) ($body['symbol'] ?? '')));
        if (!$wlId || $symbol === '') { Response::error('watchlist_id and symbol required', 422); }
        $owns = $db->scalar('SELECT 1 FROM watchlists WHERE id = ? AND user_id = ?', [$wlId, $user['id']]);
        if (!$owns) { Response::error('Watchlist not found', 404); }
        try {
            $id = $db->insert('watchlist_items', [
                'watchlist_id' => $wlId,
                'symbol'       => $symbol,
                'added_price'  => MarketData::lastPrice($symbol),
            ]);
        } catch (Throwable) {
            Response::error('Symbol already in watchlist', 409);
        }
        Response::json(['id' => $id, 'symbol' => $symbol], 201);
    }
    if ($method === 'DELETE' && $itemId) {
        $db->run('DELETE wi FROM watchlist_items wi
                  JOIN watchlists w ON w.id = wi.watchlist_id
                  WHERE wi.id = ? AND w.user_id = ?', [(int) $itemId, $user['id']]);
        Response::json(['message' => 'Removed']);
    }
    Response::error('Invalid item operation', 400);
}

// Collection / item operations on watchlists themselves
if ($method === 'GET') {
    $lists = $db->fetchAll('SELECT * FROM watchlists WHERE user_id = ? ORDER BY created_at', [$user['id']]);
    foreach ($lists as &$wl) {
        $wl['items'] = $db->fetchAll(
            'SELECT id,symbol,added_price,created_at FROM watchlist_items WHERE watchlist_id = ?',
            [$wl['id']]
        );
    }
    unset($wl);
    Response::json($lists);
}

if ($method === 'POST') {
    $count = (int) $db->scalar('SELECT COUNT(*) FROM watchlists WHERE user_id = ?', [$user['id']]);
    $max = (int) ($user['max_watchlists'] ?? 1);
    if ($count >= $max) {
        Response::error("Your plan allows up to $max watchlist(s). Upgrade for more.", 403);
    }
    $name = trim((string) ($body['name'] ?? 'My Watchlist')) ?: 'My Watchlist';
    $marketType = in_array(($body['market'] ?? 'crypto'), ['crypto','forex','stocks','commodities'], true) ? ($body['market'] ?? 'crypto') : 'crypto';
    $id = $db->insert('watchlists', ['user_id' => $user['id'], 'name' => $name, 'market' => $marketType]);
    Response::json(['id' => $id, 'name' => $name, 'market' => $marketType, 'items' => []], 201);
}

if ($method === 'DELETE' && $action) {
    $db->run('DELETE FROM watchlists WHERE id = ? AND user_id = ?', [(int) $action, $user['id']]);
    Response::json(['message' => 'Watchlist deleted']);
}

Response::error('Invalid watchlist operation', 400);
