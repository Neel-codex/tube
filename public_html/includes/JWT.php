<?php
declare(strict_types=1);

/**
 * JWT - dependency-free HS256 JSON Web Token implementation.
 * No Composer / no external library required (cPanel friendly).
 */
final class JWT
{
    private const ALG = 'HS256';

    public static function issue(array $claims, ?int $ttl = null): string
    {
        $ttl = $ttl ?? JWT_TTL;
        $now = time();
        $header  = ['alg' => self::ALG, 'typ' => 'JWT'];
        $payload = array_merge($claims, [
            'iss' => JWT_ISSUER,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
            'jti' => bin2hex(random_bytes(8)),
        ]);

        $segments = [
            self::b64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)),
            self::b64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];
        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, JWT_SECRET, true);
        $segments[] = self::b64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * Validate & decode. Returns claims array or null if invalid/expired.
     */
    public static function verify(?string $token): ?array
    {
        if (!$token) {
            return null;
        }
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$h, $p, $s] = $parts;
        $signingInput = $h . '.' . $p;
        $expected = hash_hmac('sha256', $signingInput, JWT_SECRET, true);
        $provided = self::b64UrlDecode($s);
        if (!hash_equals($expected, $provided)) {
            return null;
        }
        $payload = json_decode(self::b64UrlDecode($p), true);
        if (!is_array($payload)) {
            return null;
        }
        $now = time();
        if (isset($payload['nbf']) && $now < (int) $payload['nbf']) {
            return null;
        }
        if (isset($payload['exp']) && $now >= (int) $payload['exp']) {
            return null;
        }
        return $payload;
    }

    private static function b64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }
}
