<?php
declare(strict_types=1);
/**
 * Manual USDT (BEP20) payment workflow.
 *
 * /api/payments/wallet           GET   active wallet + QR for a plan
 * /api/payments/submit           POST  submit txid + amount + screenshot
 * /api/payments/mine             GET   current user's payment requests
 */

$db   = Database::instance();
$method = $ctx['method'];
$action = $ctx['action'];
$body   = $ctx['body'];

if ($action === 'wallet' && $method === 'GET') {
    $wallet = $db->fetch('SELECT id,network,currency,address,label,qr_image FROM wallet_settings WHERE is_active = 1 ORDER BY id LIMIT 1');
    if (!$wallet) { Response::error('No active wallet configured. Contact support.', 503); }
    Response::json(['wallet' => Security::clean($wallet)]);
}

$user = Auth::require();

if ($action === 'mine' && $method === 'GET') {
    $rows = $db->fetchAll(
        'SELECT pr.*, p.name AS plan_name FROM payment_requests pr
         JOIN plans p ON p.id = pr.plan_id
         WHERE pr.user_id = ? ORDER BY pr.created_at DESC',
        [$user['id']]
    );
    Response::json(Security::clean($rows));
}

if ($action === 'submit' && $method === 'POST') {
    $planId = (int) ($body['plan_id'] ?? ($_POST['plan_id'] ?? 0));
    $txid   = trim((string) ($body['txid'] ?? ($_POST['txid'] ?? '')));
    $amount = (float) ($body['amount_usdt'] ?? ($_POST['amount_usdt'] ?? 0));

    $plan = $db->fetch('SELECT * FROM plans WHERE id = ? AND is_active = 1', [$planId]);
    if (!$plan) { Response::error('Invalid plan', 422); }
    if (strlen($txid) < 10) { Response::error('A valid transaction hash (TXID) is required', 422); }
    if ($amount <= 0) { Response::error('Amount must be greater than zero', 422); }

    // duplicate txid guard
    if ($db->scalar('SELECT 1 FROM payment_requests WHERE txid = ?', [$txid])) {
        Response::error('This TXID has already been submitted', 409);
    }

    // optional screenshot upload
    $screenshot = null;
    if (!empty($_FILES['screenshot'])) {
        try {
            $screenshot = Security::handleImageUpload($_FILES['screenshot'], 'payments', (int) $user['id']);
        } catch (Throwable $e) {
            Response::error('Screenshot: ' . $e->getMessage(), 422);
        }
    }

    $wallet = $db->fetch('SELECT id FROM wallet_settings WHERE is_active = 1 ORDER BY id LIMIT 1');
    $id = $db->insert('payment_requests', [
        'user_id'     => $user['id'],
        'plan_id'     => $planId,
        'wallet_id'   => $wallet['id'] ?? null,
        'amount_usdt' => $amount,
        'txid'        => $txid,
        'screenshot'  => $screenshot,
        'status'      => 'pending',
    ]);
    Helpers::log((int) $user['id'], 'payment_submitted', 'payment_requests', ['id' => $id, 'plan' => $plan['code']]);
    Helpers::notify((int) $user['id'], 'Payment submitted',
        'Your payment for the ' . $plan['name'] . ' plan is pending admin review.', 'payment');

    Response::json([
        'id'      => $id,
        'status'  => 'pending',
        'message' => 'Payment submitted. Your subscription activates automatically once an admin approves it.',
    ], 201);
}

Response::error('Invalid payment operation', 400);
