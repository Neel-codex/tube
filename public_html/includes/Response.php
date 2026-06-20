<?php
declare(strict_types=1);

/** Response - standardised JSON API responses. */
final class Response
{
    public static function json(mixed $data, int $status = 200, array $meta = []): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        $payload = ['success' => $status >= 200 && $status < 300, 'data' => $data];
        if ($meta) {
            $payload['meta'] = $meta;
        }
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error(string $message, int $status = 400, array $extra = []): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge([
            'success' => false,
            'error'   => $message,
        ], $extra), JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** Read and decode JSON request body. */
    public static function body(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') {
            return $_POST;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : $_POST;
    }
}
