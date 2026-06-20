<?php
declare(strict_types=1);

/**
 * TradeVision Pro - REST API front controller.
 *
 * Routing convention:  /api/{resource}/{action?}/{id?}
 * Examples:
 *   POST /api/auth/register
 *   POST /api/auth/login
 *   GET  /api/scanner?timeframe=15m&rating=strong_buy
 *   GET  /api/signals?style=intraday
 *   GET  /api/analysis/BTCUSDT?timeframe=1h
 *   GET  /api/watchlists
 *   POST /api/payments/submit
 *   POST /api/admin/payments/approve
 */

require_once __DIR__ . '/../includes/bootstrap.php';

Security::headers(true);

// CORS (same-origin by default; APP_URL allowed)
header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Global rate limit per IP
if (!Security::rateLimit('api:' . Security::clientIp())) {
    Response::error('Rate limit exceeded. Slow down.', 429);
}

// Parse path
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = '/api';
$path = $uri;
if (str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}
$segments = array_values(array_filter(explode('/', trim($path, '/')), static fn ($s) => $s !== ''));
$resource = $segments[0] ?? '';
$action   = $segments[1] ?? '';
$id       = $segments[2] ?? '';
$method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $handlerFile = __DIR__ . '/routes/' . preg_replace('/[^a-z]/', '', strtolower($resource)) . '.php';
    if ($resource === '' ) {
        Response::json([
            'name'    => APP_NAME . ' API',
            'version' => '1.0',
            'status'  => 'online',
            'time'    => gmdate('c'),
        ]);
    }
    if (!is_file($handlerFile)) {
        Response::error('Unknown endpoint: ' . $resource, 404);
    }
    $ctx = [
        'method'   => $method,
        'action'   => $action,
        'id'       => $id,
        'segments' => $segments,
        'body'     => Response::body(),
        'query'    => $_GET,
    ];
    require $handlerFile;   // each route file defines and calls handle($ctx)
} catch (Throwable $e) {
    error_log('API error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (TVP_DEBUG) {
        Response::error('Server error: ' . $e->getMessage(), 500, ['trace' => $e->getTraceAsString()]);
    }
    Response::error('Internal server error.', 500);
}
