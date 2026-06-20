<?php
declare(strict_types=1);
/**
 * Admin API. All actions require role=admin.
 *
 * /api/admin/stats
 * /api/admin/users                 GET list, /api/admin/users/{id} PATCH (suspend/activate/extend)
 * /api/admin/payments              GET pending list
 * /api/admin/payments/approve      POST {id}
 * /api/admin/payments/reject       POST {id, note}
 * /api/admin/wallets               GET list, POST create/update, DELETE
 * /api/admin/settings              GET all, POST update {key:value,...}
 * /api/admin/announcements         GET, POST, DELETE
 * /api/admin/signals/{id}          DELETE
 * /api/admin/logs                  GET recent activity
 */

$admin  = Auth::requireAdmin();
$db     = Database::instance();
$method = $ctx['method'];
$action = $ctx['action'];
$id     = $ctx['id'];
$body   = $ctx['body'];

switch ($action) {
    case 'stats':
        Response::json([
            'users'            => (int) $db->scalar('SELECT COUNT(*) FROM users'),
            'active_subs'      => (int) $db->scalar("SELECT COUNT(*) FROM subscriptions WHERE status='active' AND expires_at > NOW()"),
            'pending_payments' => (int) $db->scalar("SELECT COUNT(*) FROM payment_requests WHERE status='pending'"),
            'signals_today'    => (int) $db->scalar('SELECT COUNT(*) FROM signals WHERE created_at >= CURDATE()'),
            'scanned_symbols'  => (int) $db->scalar('SELECT COUNT(DISTINCT symbol) FROM scanner_results'),
            'revenue_usdt'     => (float) $db->scalar("SELECT COALESCE(SUM(amount_usdt),0) FROM payment_requests WHERE status='approved'"),
        ]);
        break;

    case 'users':
        if ($method === 'GET') {
            $search = '%' . ($ctx['query']['q'] ?? '') . '%';
            $rows = $db->fetchAll(
                'SELECT u.id,u.full_name,u.email,u.role,u.status,u.created_at,u.last_login_at,
                        p.code AS plan_code, p.name AS plan_name
                 FROM users u JOIN plans p ON p.id = u.plan_id
                 WHERE u.email LIKE ? OR u.full_name LIKE ?
                 ORDER BY u.created_at DESC LIMIT 200',
                [$search, $search]
            );
            Response::json(Security::clean($rows));
        }
        if ($method === 'PATCH' && $id) {
            $uid = (int) $id;
            if (!empty($body['status']) && in_array($body['status'], ['active','suspended'], true)) {
                $db->update('users', ['status' => $body['status']], ['id' => $uid]);
                Helpers::log((int) $admin['id'], 'user_' . $body['status'], 'users', ['user_id' => $uid]);
            }
            if (!empty($body['plan_id'])) {
                admin_assign_plan($db, $uid, (int) $body['plan_id'], (int) ($body['extend_days'] ?? 0));
            }
            Response::json(['message' => 'User updated']);
        }
        Response::error('Invalid users operation', 400);
        break;

    case 'payments':
        if ($method === 'GET') {
            $status = $ctx['query']['status'] ?? 'pending';
            $rows = $db->fetchAll(
                'SELECT pr.*, u.email, u.full_name, p.name AS plan_name, p.code AS plan_code, p.duration_days
                 FROM payment_requests pr
                 JOIN users u ON u.id = pr.user_id
                 JOIN plans p ON p.id = pr.plan_id
                 WHERE pr.status = ? ORDER BY pr.created_at DESC LIMIT 200',
                [$status]
            );
            Response::json(Security::clean($rows));
        }
        if ($method === 'POST' && $id === 'approve') {
            $pid = (int) ($body['id'] ?? 0);
            admin_review_payment($db, $admin, $pid, true, '');
            Response::json(['message' => 'Payment approved and subscription activated']);
        }
        if ($method === 'POST' && $id === 'reject') {
            $pid = (int) ($body['id'] ?? 0);
            admin_review_payment($db, $admin, $pid, false, (string) ($body['note'] ?? ''));
            Response::json(['message' => 'Payment rejected']);
        }
        Response::error('Invalid payments operation', 400);
        break;

    case 'wallets':
        if ($method === 'GET') {
            Response::json(Security::clean($db->fetchAll('SELECT * FROM wallet_settings ORDER BY id')));
        }
        if ($method === 'POST') {
            $addr = trim((string) ($body['address'] ?? ''));
            if ($addr === '') { Response::error('Wallet address required', 422); }
            if (!empty($body['id'])) {
                $db->update('wallet_settings', [
                    'network'  => $body['network'] ?? 'BEP20',
                    'currency' => $body['currency'] ?? 'USDT',
                    'address'  => $addr,
                    'label'    => $body['label'] ?? null,
                    'is_active'=> !empty($body['is_active']) ? 1 : 0,
                ], ['id' => (int) $body['id']]);
                Response::json(['message' => 'Wallet updated']);
            }
            $newId = $db->insert('wallet_settings', [
                'network'  => $body['network'] ?? 'BEP20',
                'currency' => $body['currency'] ?? 'USDT',
                'address'  => $addr,
                'label'    => $body['label'] ?? null,
                'is_active'=> !empty($body['is_active']) ? 1 : 1,
            ]);
            Response::json(['id' => $newId], 201);
        }
        if ($method === 'DELETE' && $id) {
            $db->run('DELETE FROM wallet_settings WHERE id = ?', [(int) $id]);
            Response::json(['message' => 'Wallet deleted']);
        }
        Response::error('Invalid wallet operation', 400);
        break;

    case 'settings':
        if ($method === 'GET') {
            $rows = $db->fetchAll('SELECT skey, svalue, group_name FROM settings ORDER BY group_name, skey');
            Response::json($rows);
        }
        if ($method === 'POST') {
            $updates = is_array($body['settings'] ?? null) ? $body['settings'] : $body;
            foreach ($updates as $k => $v) {
                if (!is_string($k)) { continue; }
                Helpers::setSetting($k, (string) $v);
            }
            Helpers::log((int) $admin['id'], 'settings_update', 'settings');
            Response::json(['message' => 'Settings saved']);
        }
        Response::error('Invalid settings operation', 400);
        break;

    case 'announcements':
        if ($method === 'GET') {
            Response::json(Security::clean($db->fetchAll('SELECT * FROM announcements ORDER BY created_at DESC')));
        }
        if ($method === 'POST') {
            $aid = $db->insert('announcements', [
                'title' => substr((string) ($body['title'] ?? ''), 0, 160),
                'body'  => (string) ($body['body'] ?? ''),
                'level' => in_array(($body['level'] ?? 'info'), ['info','success','warning','danger'], true) ? ($body['level'] ?? 'info') : 'info',
                'is_active' => !empty($body['is_active']) ? 1 : 1,
            ]);
            Response::json(['id' => $aid], 201);
        }
        if ($method === 'DELETE' && $id) {
            $db->run('DELETE FROM announcements WHERE id = ?', [(int) $id]);
            Response::json(['message' => 'Deleted']);
        }
        Response::error('Invalid announcements operation', 400);
        break;

    case 'signals':
        if ($method === 'DELETE' && $id) {
            $db->run('DELETE FROM signals WHERE id = ?', [(int) $id]);
            Response::json(['message' => 'Signal deleted']);
        }
        Response::error('Invalid signals operation', 400);
        break;

    case 'logs':
        $rows = $db->fetchAll(
            'SELECT al.*, u.email FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             ORDER BY al.created_at DESC LIMIT 200'
        );
        Response::json(Security::clean($rows));
        break;

    default:
        Response::error('Unknown admin action', 404);
}

// ---------------------------------------------------------------------
function admin_review_payment(Database $db, array $admin, int $pid, bool $approve, string $note): void
{
    $pr = $db->fetch('SELECT * FROM payment_requests WHERE id = ?', [$pid]);
    if (!$pr) { Response::error('Payment request not found', 404); }
    if ($pr['status'] !== 'pending') { Response::error('Payment already reviewed', 409); }

    $db->beginTransaction();
    try {
        $db->update('payment_requests', [
            'status'      => $approve ? 'approved' : 'rejected',
            'admin_note'  => substr($note, 0, 255),
            'reviewed_by' => $admin['id'],
            'reviewed_at' => date('Y-m-d H:i:s'),
        ], ['id' => $pid]);

        if ($approve) {
            $plan = $db->fetch('SELECT * FROM plans WHERE id = ?', [$pr['plan_id']]);
            admin_assign_plan($db, (int) $pr['user_id'], (int) $pr['plan_id'], (int) $plan['duration_days']);
            Helpers::notify((int) $pr['user_id'], 'Payment approved',
                'Your ' . $plan['name'] . ' subscription is now active. Welcome aboard!', 'payment');
        } else {
            Helpers::notify((int) $pr['user_id'], 'Payment rejected',
                'Your payment was not approved. ' . ($note ?: 'Please contact support.'), 'payment');
        }
        Helpers::log((int) $admin['id'], $approve ? 'payment_approved' : 'payment_rejected', 'payment_requests', ['id' => $pid]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function admin_assign_plan(Database $db, int $userId, int $planId, int $days): void
{
    $now = time();
    // extend from current active expiry if later than now
    $current = $db->scalar(
        "SELECT MAX(expires_at) FROM subscriptions WHERE user_id = ? AND status='active'",
        [$userId]
    );
    $start = ($current && strtotime((string) $current) > $now) ? strtotime((string) $current) : $now;
    $expires = $start + max($days, 0) * 86400;

    // expire previous active subs
    $db->run("UPDATE subscriptions SET status='expired' WHERE user_id = ? AND status='active'", [$userId]);
    $db->insert('subscriptions', [
        'user_id'    => $userId,
        'plan_id'    => $planId,
        'status'     => 'active',
        'started_at' => date('Y-m-d H:i:s', $now),
        'expires_at' => date('Y-m-d H:i:s', $expires),
        'source'     => 'manual_usdt',
    ]);
    $db->update('users', ['plan_id' => $planId], ['id' => $userId]);
}
