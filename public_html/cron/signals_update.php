<?php
declare(strict_types=1);
/**
 * cron/signals_update.php - track active signals against live price and
 * update their status (tp1/tp2/tp3 hit, sl hit, expired).
 *
 * Suggested schedule:  every 2 minutes
 */
if (PHP_SAPI !== 'cli' && empty($_GET['cron_key'])) { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../includes/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    $expected = (string) Helpers::setting('cron_key', '');
    if ($expected === '' || ($_GET['cron_key'] ?? '') !== $expected) { http_response_code(403); exit('Forbidden.'); }
}

$db = Database::instance();
$active = $db->fetchAll("SELECT * FROM signals WHERE status IN ('active','tp1_hit','tp2_hit') ORDER BY created_at DESC LIMIT 500");
$updated = 0;
$priceCache = [];

foreach ($active as $sig) {
    $price = $priceCache[$sig['symbol']] ??= MarketData::lastPrice($sig['symbol']);
    if ($price === null) { continue; }

    $long = $sig['direction'] === 'long';
    $newStatus = $sig['status'];

    // Stop loss
    if (($long && $price <= (float) $sig['stop_loss']) || (!$long && $price >= (float) $sig['stop_loss'])) {
        $newStatus = 'sl_hit';
    } else {
        // Take profits (progressive)
        $tp1 = (float) $sig['tp1']; $tp2 = (float) $sig['tp2']; $tp3 = (float) $sig['tp3'];
        if (($long && $price >= $tp3) || (!$long && $price <= $tp3)) { $newStatus = 'tp3_hit'; }
        elseif (($long && $price >= $tp2) || (!$long && $price <= $tp2)) { $newStatus = 'tp2_hit'; }
        elseif (($long && $price >= $tp1) || (!$long && $price <= $tp1)) { $newStatus = 'tp1_hit'; }
    }

    // Expire stale active signals (older than 3 days, still untouched)
    if ($newStatus === 'active' && (time() - strtotime($sig['created_at'])) > 3 * 86400) {
        $newStatus = 'expired';
    }

    if ($newStatus !== $sig['status']) {
        $db->update('signals', ['status' => $newStatus], ['id' => $sig['id']]);
        $updated++;
    }
}

$msg = "[" . gmdate('c') . "] signals_update: scanned " . count($active) . ", updated $updated";
@file_put_contents(LOG_PATH . '/signals.log', $msg . "\n", FILE_APPEND);
echo $msg . "\n";
