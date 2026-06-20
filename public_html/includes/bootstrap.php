<?php
declare(strict_types=1);

/**
 * bootstrap.php - single include that wires up the whole app.
 * Every entrypoint (pages, API, cron) starts with:
 *   require_once __DIR__ . '/../includes/bootstrap.php';
 */

require_once dirname(__DIR__) . '/config/config.php';

spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . '/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

// Engine classes live in includes/engine
spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . '/engine/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

// Ensure storage dirs exist
foreach ([CACHE_PATH, LOG_PATH] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}
