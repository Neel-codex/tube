<?php
declare(strict_types=1);

/**
 * Auth - registration, login, JWT session, plan/subscription gating.
 */
final class Auth
{
    /** Register a new user. Returns [user, token]. */
    public static function register(string $name, string $email, string $password): array
    {
        $db = Database::instance();
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email address is required.');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters.');
        }
        if ($db->scalar('SELECT 1 FROM users WHERE email = ?', [$email])) {
            throw new RuntimeException('An account with that email already exists.');
        }
        $freePlanId = (int) ($db->scalar("SELECT id FROM plans WHERE code='free' LIMIT 1") ?: 1);
        $uuid = self::uuid();
        $id = $db->insert('users', [
            'uuid'          => $uuid,
            'full_name'     => trim($name) ?: 'Trader',
            'email'         => $email,
            'password_hash' => Security::hashPassword($password),
            'plan_id'       => $freePlanId,
            'role'          => 'user',
            'status'        => 'active',
            'email_verified'=> 0,
        ]);
        $db->insert('subscriptions', [
            'user_id'    => $id,
            'plan_id'    => $freePlanId,
            'status'     => 'active',
            'started_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', time() + 3650 * 86400),
            'source'     => 'signup',
        ]);
        Helpers::log($id, 'register', 'user');
        $user = self::findUser($id);
        return [$user, self::tokenFor($user)];
    }

    /** Authenticate by email + password. Returns [user, token]. */
    public static function login(string $email, string $password): array
    {
        $db = Database::instance();
        $email = strtolower(trim($email));
        $ip = Security::clientIp();
        if (!Security::rateLimit('login:' . $ip, 10, 300)) {
            throw new RuntimeException('Too many login attempts. Try again later.');
        }
        $user = $db->fetch('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);
        if (!$user || !Security::verifyPassword($password, $user['password_hash'])) {
            throw new RuntimeException('Invalid email or password.');
        }
        if ($user['status'] === 'suspended') {
            throw new RuntimeException('Your account has been suspended.');
        }
        $db->update('users', ['last_login_at' => date('Y-m-d H:i:s')], ['id' => $user['id']]);
        Helpers::log((int) $user['id'], 'login', 'user');
        return [self::publicUser($user), self::tokenFor($user)];
    }

    /** Build a JWT for a user row. */
    public static function tokenFor(array $user): string
    {
        return JWT::issue([
            'sub'   => (int) $user['id'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ]);
    }

    /** Extract bearer token from Authorization header or cookie. */
    public static function bearerToken(): ?string
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders() ?: [];
        }
        $auth = $headers['Authorization'] ?? $headers['authorization']
            ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (is_string($auth) && preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
            return trim($m[1]);
        }
        return $_COOKIE['tvp_token'] ?? null;
    }

    /** Return the current authenticated user (public) or null. */
    public static function user(): ?array
    {
        $claims = JWT::verify(self::bearerToken());
        if (!$claims || empty($claims['sub'])) {
            return null;
        }
        return self::findUser((int) $claims['sub']);
    }

    /** Require auth; emits 401 JSON and exits if missing. */
    public static function require(): array
    {
        $user = self::user();
        if (!$user) {
            Response::error('Authentication required.', 401);
        }
        if ($user['status'] === 'suspended') {
            Response::error('Account suspended.', 403);
        }
        return $user;
    }

    /** Require admin role; emits 403 if not admin. */
    public static function requireAdmin(): array
    {
        $user = self::require();
        if ($user['role'] !== 'admin') {
            Response::error('Administrator access required.', 403);
        }
        return $user;
    }

    /** Load a user joined with current plan & subscription status. */
    public static function findUser(int $id): ?array
    {
        $db = Database::instance();
        $row = $db->fetch(
            'SELECT u.*, p.code AS plan_code, p.name AS plan_name, p.scanner_access,
                    p.signals_per_day, p.max_watchlists, p.max_alerts
             FROM users u JOIN plans p ON p.id = u.plan_id WHERE u.id = ? LIMIT 1',
            [$id]
        );
        return $row ? self::publicUser($row) : null;
    }

    /** Strip sensitive fields. */
    public static function publicUser(array $u): array
    {
        unset($u['password_hash']);
        // Resolve active subscription expiry
        $db = Database::instance();
        $sub = $db->fetch(
            "SELECT expires_at, status FROM subscriptions
             WHERE user_id = ? AND status='active' ORDER BY expires_at DESC LIMIT 1",
            [$u['id']]
        );
        $u['subscription_expires_at'] = $sub['expires_at'] ?? null;
        $u['subscription_active'] = $sub && (strtotime((string) $sub['expires_at']) > time());
        return $u;
    }

    /** RFC4122 v4 UUID. */
    public static function uuid(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }
}
