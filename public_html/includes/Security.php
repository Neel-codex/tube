<?php
declare(strict_types=1);

/**
 * Security - CSRF, XSS sanitisation, secure sessions, rate limiting,
 * upload validation and security headers.
 */
final class Security
{
    /** Send strict security headers (call early in every entrypoint). */
    public static function headers(bool $isApi = false): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 1; mode=block');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        if (!$isApi) {
            // CSP allows the CDNs the front-end relies on.
            header("Content-Security-Policy: default-src 'self'; "
                . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://unpkg.com https://s3.tradingview.com https://www.tradingview.com; "
                . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
                . "font-src 'self' https://fonts.gstatic.com data:; "
                . "img-src 'self' data: https:; "
                . "connect-src 'self' https://api.binance.com https://fapi.binance.com https://api.coingecko.com https://query1.finance.yahoo.com; "
                . "frame-src https://www.tradingview.com https://s.tradingview.com;");
        }
    }

    /** Start a hardened session. */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('TVP_SESS');
        session_start();
        if (empty($_SESSION['__created'])) {
            $_SESSION['__created'] = time();
            session_regenerate_id(true);
        }
    }

    /** Get (and lazily create) the CSRF token. */
    public static function csrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Validate a CSRF token (timing-safe). */
    public static function verifyCsrf(?string $token): bool
    {
        self::startSession();
        return !empty($token)
            && !empty($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    /** Recursively sanitise output to prevent XSS. */
    public static function clean(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([self::class, 'clean'], $value);
        }
        if (is_string($value)) {
            return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return $value;
    }

    /** Escape for HTML output. */
    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** Hash a password with pepper. */
    public static function hashPassword(string $password): string
    {
        return password_hash($password . PASSWORD_PEPPER, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password . PASSWORD_PEPPER, $hash);
    }

    /** Client IP, respecting common proxy headers safely. */
    public static function clientIp(): string
    {
        $candidates = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($candidates as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    /**
     * DB-backed fixed-window rate limiter.
     * Returns true if the request is allowed.
     */
    public static function rateLimit(string $key, int $max = RATE_LIMIT_REQUESTS, int $window = RATE_LIMIT_WINDOW): bool
    {
        $db = Database::instance();
        $now = time();
        $windowStart = $now - ($now % $window);
        $rlKey = substr($key, 0, 120);
        $row = $db->fetch('SELECT id, hits, window_start FROM rate_limits WHERE rl_key = ?', [$rlKey]);
        if (!$row) {
            $db->run('INSERT INTO rate_limits (rl_key, hits, window_start) VALUES (?,?,?)
                      ON DUPLICATE KEY UPDATE hits = 1, window_start = VALUES(window_start)',
                [$rlKey, 1, $windowStart]);
            return true;
        }
        if ((int) $row['window_start'] < $windowStart) {
            $db->run('UPDATE rate_limits SET hits = 1, window_start = ? WHERE id = ?', [$windowStart, $row['id']]);
            return true;
        }
        if ((int) $row['hits'] >= $max) {
            return false;
        }
        $db->run('UPDATE rate_limits SET hits = hits + 1 WHERE id = ?', [$row['id']]);
        return true;
    }

    /**
     * Validate an uploaded image. Returns the safe stored path (relative)
     * or throws RuntimeException.
     */
    public static function handleImageUpload(array $file, string $subdir, ?int $userId = null): string
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException('Invalid upload parameters.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed with error code ' . $file['error']);
        }
        if ($file['size'] > MAX_UPLOAD_BYTES) {
            throw new RuntimeException('File too large (max 5MB).');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        $allowed = explode(',', ALLOWED_IMAGE_MIME);
        if (!in_array($mime, $allowed, true)) {
            throw new RuntimeException('Unsupported file type.');
        }
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'bin',
        };
        $dir = UPLOAD_PATH . '/' . trim($subdir, '/');
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create upload directory.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            // Fallback for CLI/test contexts
            if (!rename($file['tmp_name'], $dest)) {
                throw new RuntimeException('Failed to store uploaded file.');
            }
        }
        @chmod($dest, 0644);
        $relative = 'uploads/' . trim($subdir, '/') . '/' . $name;

        // Record in DB
        try {
            Database::instance()->insert('uploaded_files', [
                'user_id'    => $userId,
                'category'   => in_array($subdir, ['charts', 'payments', 'profiles'], true)
                                ? rtrim($subdir, 's') : 'other',
                'path'       => $relative,
                'mime'       => $mime,
                'size_bytes' => (int) $file['size'],
            ]);
        } catch (Throwable) { /* non-fatal */ }

        return $relative;
    }
}
