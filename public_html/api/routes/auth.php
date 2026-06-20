<?php
declare(strict_types=1);
/** /api/auth/{register|login|me|logout|telegram} */

$method = $ctx['method'];
$action = $ctx['action'];
$body   = $ctx['body'];

switch ($action) {
    case 'register':
        if ($method !== 'POST') { Response::error('Method not allowed', 405); }
        try {
            [$user, $token] = Auth::register(
                (string) ($body['name'] ?? ''),
                (string) ($body['email'] ?? ''),
                (string) ($body['password'] ?? '')
            );
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 422);
        }
        setcookie('tvp_token', $token, [
            'expires' => time() + JWT_TTL, 'path' => '/', 'httponly' => true,
            'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']),
        ]);
        Response::json(['user' => Security::clean($user), 'token' => $token], 201);
        break;

    case 'login':
        if ($method !== 'POST') { Response::error('Method not allowed', 405); }
        try {
            [$user, $token] = Auth::login(
                (string) ($body['email'] ?? ''),
                (string) ($body['password'] ?? '')
            );
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 401);
        }
        setcookie('tvp_token', $token, [
            'expires' => time() + JWT_TTL, 'path' => '/', 'httponly' => true,
            'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']),
        ]);
        Response::json(['user' => Security::clean($user), 'token' => $token]);
        break;

    case 'me':
        $user = Auth::require();
        Response::json(['user' => Security::clean($user)]);
        break;

    case 'logout':
        setcookie('tvp_token', '', ['expires' => time() - 3600, 'path' => '/']);
        Response::json(['message' => 'Logged out']);
        break;

    case 'telegram':
        // Save a user's telegram chat id for alerts
        $user = Auth::require();
        if ($method !== 'POST') { Response::error('Method not allowed', 405); }
        $chatId = preg_replace('/[^0-9\-]/', '', (string) ($body['chat_id'] ?? ''));
        Database::instance()->update('users', ['telegram_chat_id' => $chatId], ['id' => $user['id']]);
        Response::json(['message' => 'Telegram linked']);
        break;

    default:
        Response::error('Unknown auth action', 404);
}
