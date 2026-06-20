<?php
declare(strict_types=1);
/**
 * /api/alerts        GET list, POST create
 * /api/alerts/{id}   DELETE, PATCH (toggle active)
 */

$user = Auth::require();
$db   = Database::instance();
$method = $ctx['method'];
$action = $ctx['action'];
$body   = $ctx['body'];

if ($method === 'GET') {
    $rows = $db->fetchAll('SELECT * FROM alerts WHERE user_id = ? ORDER BY created_at DESC', [$user['id']]);
    foreach ($rows as &$r) { $r['channels'] = json_decode((string) $r['channels'], true) ?: []; }
    unset($r);
    Response::json($rows);
}

if ($method === 'POST') {
    $count = (int) $db->scalar('SELECT COUNT(*) FROM alerts WHERE user_id = ?', [$user['id']]);
    $max = (int) ($user['max_alerts'] ?? 5);
    if ($count >= $max) {
        Response::error("Your plan allows up to $max alert(s). Upgrade for more.", 403);
    }
    $type = $body['alert_type'] ?? '';
    $valid = ['new_signal','breakout','trend_change','zone_touch','volume_spike','price_above','price_below'];
    if (!in_array($type, $valid, true)) { Response::error('Invalid alert_type', 422); }
    $channels = array_values(array_intersect(
        (array) ($body['channels'] ?? ['browser']),
        ['browser','email','telegram']
    )) ?: ['browser'];
    $id = $db->insert('alerts', [
        'user_id'         => $user['id'],
        'symbol'          => isset($body['symbol']) ? strtoupper(preg_replace('/[^A-Z0-9=\.\-]/i', '', (string) $body['symbol'])) : null,
        'alert_type'      => $type,
        'condition_value' => isset($body['condition_value']) ? (float) $body['condition_value'] : null,
        'channels'        => json_encode($channels),
        'is_active'       => 1,
    ]);
    Response::json(['id' => $id], 201);
}

if ($method === 'PATCH' && $action) {
    $active = !empty($body['is_active']) ? 1 : 0;
    $db->update('alerts', ['is_active' => $active], ['id' => (int) $action, 'user_id' => $user['id']]);
    Response::json(['message' => 'Updated', 'is_active' => $active]);
}

if ($method === 'DELETE' && $action) {
    $db->run('DELETE FROM alerts WHERE id = ? AND user_id = ?', [(int) $action, $user['id']]);
    Response::json(['message' => 'Alert deleted']);
}

Response::error('Invalid alert operation', 400);
