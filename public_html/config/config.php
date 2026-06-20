<?php
/**
 * TradeVision Pro - Central Configuration
 * -------------------------------------------------------------
 * Copy this file's values and edit for your cPanel environment.
 * Database credentials are created in cPanel > MySQL Databases.
 */

declare(strict_types=1);

// ---- Environment ----------------------------------------------------
define('TVP_ENV', getenv('TVP_ENV') ?: 'production');            // production | development
define('TVP_DEBUG', TVP_ENV === 'development');

// ---- Database (edit these) ------------------------------------------
define('DB_HOST', getenv('TVP_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('TVP_DB_NAME') ?: 'tradevision');            // your cPanel DB name (often prefixed)
define('DB_USER', getenv('TVP_DB_USER') ?: 'tradevision_user');       // your cPanel DB user
define('DB_PASS', getenv('TVP_DB_PASS') ?: 'CHANGE_ME_STRONG_PASSWORD');
define('DB_CHARSET', 'utf8mb4');
// Optional: set a unix socket path if your host requires it (leave '' for default TCP/host).
define('DB_SOCKET', getenv('TVP_DB_SOCKET') ?: '');

// ---- Application ----------------------------------------------------
define('APP_NAME', 'TradeVision Pro');
// Auto-detect base URL; override if running in a sub-folder.
define('APP_URL', (function () {
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
})());

// ---- Security -------------------------------------------------------
// Generate a unique 64-char random string for production!
define('JWT_SECRET', 'CHANGE_ME_TO_A_LONG_RANDOM_64_CHAR_SECRET_STRING_0123456789abcdef');
define('JWT_ISSUER', 'tradevision.pro');
define('JWT_TTL', 60 * 60 * 24 * 7);          // access token lifetime (7 days)
define('PASSWORD_PEPPER', getenv('TVP_PASSWORD_PEPPER') ?: ''); // optional extra password salt; empty by default so seeded admin works

// ---- Paths ----------------------------------------------------------
define('BASE_PATH', dirname(__DIR__));                 // public_html
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('CACHE_PATH', STORAGE_PATH . '/cache');
define('LOG_PATH', STORAGE_PATH . '/logs');

// ---- Uploads --------------------------------------------------------
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);   // 5MB
define('ALLOWED_IMAGE_MIME', 'image/jpeg,image/png,image/webp');

// ---- Rate limiting --------------------------------------------------
define('RATE_LIMIT_REQUESTS', 120);            // requests
define('RATE_LIMIT_WINDOW', 60);               // per seconds

// ---- External data sources -----------------------------------------
define('BINANCE_SPOT_API', 'https://api.binance.com');
define('BINANCE_FUTURES_API', 'https://fapi.binance.com');
define('COINGECKO_API', 'https://api.coingecko.com/api/v3');
define('YAHOO_FINANCE_API', 'https://query1.finance.yahoo.com');

// ---- Error handling -------------------------------------------------
if (TVP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
if (!is_dir(LOG_PATH)) { @mkdir(LOG_PATH, 0775, true); }
ini_set('error_log', LOG_PATH . '/php-error.log');

// ---- Timezone -------------------------------------------------------
date_default_timezone_set('UTC');
