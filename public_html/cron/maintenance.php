<?php
declare(strict_types=1);
/**
 * cron/maintenance.php - housekeeping:
 *   - expire ended subscriptions and downgrade users to free
 *   - purge expired market cache
 *   - prune old scanner zone history & activity logs
 *
 * Suggested schedule:  hourly
 */
if (PHP_SAPI !== 'cli' && empty($_GET['cron_key'])) { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../includes/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    $expected = (string) Helpers::setting('cron_key', '');
    if ($expected === '' || ($_GET['cron_key'] ?? '') !== $expected) { http_response_code(403); exit('Forbidden.'); }
}

$db = Database::instance();
$freePlanId = (int) ($db->scalar("SELECT id FROM plans WHERE code='free' LIMIT 1") ?: 1);

// 1) Expire subscriptions
$expired = $db->fetchAll("SELECT id, user_id FROM subscriptions WHERE status='active' AND expires_at IS NOT NULL AND expires_at < NOW()");
foreach ($expired as $sub) {
    $db->update('subscriptions', ['status' => 'expired'], ['id' => $sub['id']]);
    // downgrade to free only if no other active sub
    $other = $db->scalar("SELECT 1 FROM subscriptions WHERE user_id = ? AND status='active' AND expires_at > NOW() LIMIT 1", [$sub['user_id']]);
    if (!$other) {
        $db->update('users', ['plan_id' => $freePlanId], ['id' => $sub['user_id']]);
        Helpers::notify((int) $sub['user_id'], 'Subscription expired', 'Your plan has expired and your account was moved to the Free plan.', 'system');
    }
}

// 2) Purge expired cache
$db->run('DELETE FROM market_cache WHERE expires_at < ?', [time()]);
foreach (glob(CACHE_PATH . '/*.json') ?: [] as $f) {
    $d = json_decode((string) @file_get_contents($f), true);
    if (!is_array($d) || ($d['__exp'] ?? 0) < time()) { @unlink($f); }
}

// 3) Prune history older than 30 days
$db->run('DELETE FROM zones WHERE detected_at < (NOW() - INTERVAL 30 DAY)');
$db->run('DELETE FROM activity_logs WHERE created_at < (NOW() - INTERVAL 90 DAY)');
$db->run('DELETE FROM notifications WHERE is_read = 1 AND created_at < (NOW() - INTERVAL 30 DAY)');

$msg = "[" . gmdate('c') . "] maintenance: expired " . count($expired) . " subscriptions, cache+history pruned";
@file_put_contents(LOG_PATH . '/maintenance.log', $msg . "\n", FILE_APPEND);
echo $msg . "\n";
