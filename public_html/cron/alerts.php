<?php
declare(strict_types=1);
/**
 * cron/alerts.php - evaluate user price/structure alerts.
 * Suggested schedule:  every 2 minutes
 */
if (PHP_SAPI !== 'cli' && empty($_GET['cron_key'])) { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../includes/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    $expected = (string) Helpers::setting('cron_key', '');
    if ($expected === '' || ($_GET['cron_key'] ?? '') !== $expected) { http_response_code(403); exit('Forbidden.'); }
}

$fired = AlertDispatcher::evaluatePriceAlerts();
$msg = "[" . gmdate('c') . "] alerts: fired $fired";
@file_put_contents(LOG_PATH . '/alerts.log', $msg . "\n", FILE_APPEND);
echo $msg . "\n";
