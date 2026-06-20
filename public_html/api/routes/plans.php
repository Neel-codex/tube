<?php
declare(strict_types=1);
/** /api/plans - public list of subscription plans. */

$db = Database::instance();
$rows = $db->fetchAll('SELECT id,code,name,price_usdt,duration_days,max_watchlists,max_alerts,signals_per_day,scanner_access,features
                       FROM plans WHERE is_active = 1 ORDER BY sort_order');
foreach ($rows as &$r) { $r['features'] = json_decode((string) $r['features'], true) ?: []; }
unset($r);
Response::json($rows);
