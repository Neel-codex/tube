<?php
declare(strict_types=1);

/** Helpers - settings access, logging, math utilities. */
final class Helpers
{
    private static array $settingsCache = [];

    /** Read a setting value with default. */
    public static function setting(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$settingsCache)) {
            return self::$settingsCache[$key];
        }
        $val = Database::instance()->scalar('SELECT svalue FROM settings WHERE skey = ? LIMIT 1', [$key]);
        $val = ($val === false) ? $default : $val;
        self::$settingsCache[$key] = $val;
        return $val;
    }

    public static function setSetting(string $key, string $value, string $group = 'general'): void
    {
        Database::instance()->run(
            'INSERT INTO settings (skey, svalue, group_name) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)',
            [$key, $value, $group]
        );
        self::$settingsCache[$key] = $value;
    }

    /** Activity log. */
    public static function log(?int $userId, string $action, ?string $entity = null, array $meta = []): void
    {
        try {
            Database::instance()->insert('activity_logs', [
                'user_id'    => $userId,
                'action'     => $action,
                'entity'     => $entity,
                'ip_address' => Security::clientIp(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                'meta'       => $meta ? json_encode($meta) : null,
            ]);
        } catch (Throwable) { /* logging must never break flow */ }
    }

    /** Create an in-app notification. */
    public static function notify(int $userId, string $title, string $body = '', string $type = 'system'): void
    {
        try {
            Database::instance()->insert('notifications', [
                'user_id' => $userId,
                'title'   => $title,
                'body'    => $body,
                'type'    => $type,
            ]);
        } catch (Throwable) {}
    }

    public static function round(float $n, int $p = 8): float
    {
        return round($n, $p);
    }

    /** Pretty price formatting based on magnitude. */
    public static function fmtPrice(float $p): string
    {
        if ($p >= 1000) return number_format($p, 2);
        if ($p >= 1)    return number_format($p, 4);
        return rtrim(rtrim(number_format($p, 8), '0'), '.');
    }
}
