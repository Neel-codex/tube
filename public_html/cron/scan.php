<?php
declare(strict_types=1);
/**
 * cron/scan.php - run the market scanner for one timeframe and generate signals.
 *
 * Usage (cPanel cron):
 *   php /home/USER/public_html/cron/scan.php 1m
 *   php /home/USER/public_html/cron/scan.php 5m
 *   php /home/USER/public_html/cron/scan.php 15m
 *   php /home/USER/public_html/cron/scan.php 1h
 *
 * Suggested schedules:
 *   every minute     -> 1m
 *   every 5 minutes  -> 5m
 *   every 15 minutes -> 15m
 *   hourly           -> 1h
 *
 * Protect against web access:
 */
if (PHP_SAPI !== 'cli' && empty($_GET['cron_key'])) {
    http_response_code(403);
    exit('CLI only.');
}

require_once __DIR__ . '/../includes/bootstrap.php';

// Optional web-trigger guard (set settings.cron_key in admin)
if (PHP_SAPI !== 'cli') {
    $expected = (string) Helpers::setting('cron_key', '');
    if ($expected === '' || ($_GET['cron_key'] ?? '') !== $expected) {
        http_response_code(403);
        exit('Forbidden.');
    }
}

$timeframe = $argv[1] ?? ($_GET['tf'] ?? '15m');
if (!in_array($timeframe, ['1m', '5m', '15m', '1h'], true)) {
    $timeframe = '15m';
}

$start = microtime(true);
$lock  = STORAGE_PATH . '/scan_' . $timeframe . '.lock';

// prevent overlapping runs
if (is_file($lock) && (time() - (int) @filemtime($lock)) < 290) {
    fwrite(STDERR, "Scan for $timeframe already running, skipping.\n");
    exit(0);
}
@file_put_contents($lock, (string) time());

try {
    $results = Scanner::run($timeframe);
    $signalsCreated = 0;

    // Generate signals from the strongest setups
    foreach ($results as $analysis) {
        // Only spend effort on directional, non-neutral candidates
        if (in_array($analysis['rating'], ['strong_buy', 'strong_sell', 'buy', 'sell'], true)) {
            $sigs = SignalEngine::generateAll($analysis);
            $signalsCreated += count($sigs);
            foreach ($sigs as $sig) {
                AlertDispatcher::onNewSignal($sig);
            }
        }
    }

    $count = count($results);
    $elapsed = round(microtime(true) - $start, 2);
    $msg = "[" . gmdate('c') . "] Scan $timeframe: $count symbols, $signalsCreated signals in {$elapsed}s";
    @file_put_contents(LOG_PATH . '/scan.log', $msg . "\n", FILE_APPEND);
    Helpers::log(null, 'cron_scan', 'scanner', ['tf' => $timeframe, 'symbols' => $count, 'signals' => $signalsCreated]);
    echo $msg . "\n";
} catch (Throwable $e) {
    @file_put_contents(LOG_PATH . '/scan.log', "[ERROR] " . $e->getMessage() . "\n", FILE_APPEND);
    fwrite(STDERR, 'Scan error: ' . $e->getMessage() . "\n");
} finally {
    @unlink($lock);
}
