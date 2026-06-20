<?php
declare(strict_types=1);
/**
 * /api/notifications        GET list, POST mark-all-read
 * /api/notifications/{id}   PATCH mark read
 */

$user = Auth::require();
$db   = Database::instance();
$method = $ctx['method'];
$action = $ctx['action'];

if ($method === 'GET') {
    $rows = $db->fetchAll('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50', [$user['id']]);
    $unread = (int) $db->scalar('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$user['id']]);
    Response::json(['notifications' => Security::clean($rows), 'unread' => $unread]);
}

if ($method === 'POST' && $action === 'read-all') {
    $db->run('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$user['id']]);
    Response::json(['message' => 'All marked read']);
}

if ($method === 'PATCH' && $action) {
    $db->update('notifications', ['is_read' => 1], ['id' => (int) $action, 'user_id' => $user['id']]);
    Response::json(['message' => 'Marked read']);
}

Response::error('Invalid notifications operation', 400);
