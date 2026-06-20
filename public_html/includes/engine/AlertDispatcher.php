<?php
declare(strict_types=1);

/**
 * AlertDispatcher - delivers alerts/notifications across channels:
 *   - browser (stored in notifications table, polled by frontend)
 *   - email   (PHP mail() or SMTP settings)
 *   - telegram (Bot API via settings.telegram_bot_token)
 *
 * Rule-based triggers only; no AI.
 */
final class AlertDispatcher
{
    /** Fan out a new signal to users with matching active alerts. */
    public static function onNewSignal(array $sig): void
    {
        $db = Database::instance();
        $alerts = $db->fetchAll(
            "SELECT * FROM alerts WHERE is_active = 1 AND alert_type = 'new_signal'
             AND (symbol IS NULL OR symbol = ?)",
            [$sig['symbol']]
        );
        $title = sprintf('New %s signal: %s %s', $sig['style'], strtoupper($sig['direction']), $sig['symbol']);
        $body  = sprintf(
            "Entry %s | SL %s | TP1 %s | TP2 %s | TP3 %s | RR %s | Confidence %s%%",
            Helpers::fmtPrice((float) $sig['entry']),
            Helpers::fmtPrice((float) $sig['stop_loss']),
            Helpers::fmtPrice((float) $sig['tp1']),
            Helpers::fmtPrice((float) $sig['tp2']),
            Helpers::fmtPrice((float) $sig['tp3']),
            $sig['risk_reward'],
            $sig['confidence']
        );
        foreach ($alerts as $alert) {
            self::deliver((int) $alert['user_id'], $title, $body, $alert);
            $db->update('alerts', ['last_triggered_at' => date('Y-m-d H:i:s')], ['id' => $alert['id']]);
        }
    }

    /** Evaluate price/volume/zone alerts against current market data. */
    public static function evaluatePriceAlerts(): int
    {
        $db = Database::instance();
        $alerts = $db->fetchAll(
            "SELECT * FROM alerts WHERE is_active = 1
             AND alert_type IN ('price_above','price_below','volume_spike','breakout','zone_touch','trend_change')
             AND symbol IS NOT NULL"
        );
        $fired = 0;
        $priceCache = [];
        foreach ($alerts as $alert) {
            $symbol = $alert['symbol'];
            // throttle: skip if triggered within last 30 min
            if ($alert['last_triggered_at'] && (time() - strtotime($alert['last_triggered_at'])) < 1800) {
                continue;
            }
            $price = $priceCache[$symbol] ??= MarketData::lastPrice($symbol);
            if ($price === null) { continue; }
            $hit = false; $msg = '';
            switch ($alert['alert_type']) {
                case 'price_above':
                    $hit = $alert['condition_value'] !== null && $price >= (float) $alert['condition_value'];
                    $msg = "$symbol crossed above " . Helpers::fmtPrice((float) $alert['condition_value']);
                    break;
                case 'price_below':
                    $hit = $alert['condition_value'] !== null && $price <= (float) $alert['condition_value'];
                    $msg = "$symbol dropped below " . Helpers::fmtPrice((float) $alert['condition_value']);
                    break;
                default:
                    // structural alerts use latest scanner_results row
                    $row = $db->fetch('SELECT * FROM scanner_results WHERE symbol = ? ORDER BY scanned_at DESC LIMIT 1', [$symbol]);
                    if ($row) {
                        $setups = json_decode((string) $row['signals'], true) ?: [];
                        $types = array_column($setups, 'type');
                        if ($alert['alert_type'] === 'volume_spike' && in_array('volume_surge', $types, true)) { $hit = true; $msg = "$symbol volume spike detected"; }
                        if ($alert['alert_type'] === 'breakout' && in_array('breakout', $types, true)) { $hit = true; $msg = "$symbol breakout detected"; }
                        if ($alert['alert_type'] === 'zone_touch' && (in_array('pullback', $types, true) || in_array('liquidity_sweep', $types, true))) { $hit = true; $msg = "$symbol touched a key zone"; }
                        if ($alert['alert_type'] === 'trend_change' && in_array('reversal', $types, true)) { $hit = true; $msg = "$symbol potential trend change"; }
                    }
            }
            if ($hit) {
                self::deliver((int) $alert['user_id'], 'Alert: ' . $symbol, $msg, $alert);
                $db->update('alerts', ['last_triggered_at' => date('Y-m-d H:i:s')], ['id' => $alert['id']]);
                $fired++;
            }
        }
        return $fired;
    }

    /** Deliver a notification across the alert's configured channels. */
    private static function deliver(int $userId, string $title, string $body, array $alert): void
    {
        $channels = json_decode((string) ($alert['channels'] ?? '[]'), true) ?: ['browser'];
        // Always store browser notification
        Helpers::notify($userId, $title, $body, 'alert');

        $user = Database::instance()->fetch('SELECT email, telegram_chat_id FROM users WHERE id = ?', [$userId]);
        if (!$user) { return; }

        if (in_array('email', $channels, true) && !empty($user['email'])) {
            self::sendEmail($user['email'], $title, $body);
        }
        if (in_array('telegram', $channels, true) && !empty($user['telegram_chat_id'])) {
            self::sendTelegram($user['telegram_chat_id'], "*$title*\n$body");
        }
    }

    public static function sendEmail(string $to, string $subject, string $body): bool
    {
        $from = Helpers::setting('support_email', 'no-reply@tradevision.pro');
        $headers = 'From: ' . $from . "\r\n" .
                   'Content-Type: text/plain; charset=UTF-8' . "\r\n" .
                   'X-Mailer: TradeVisionPro';
        // Uses host mail(); for SMTP, configure cPanel's mail or a wrapper.
        return @mail($to, '[TradeVision Pro] ' . $subject, $body, $headers);
    }

    public static function sendTelegram(string $chatId, string $text): bool
    {
        $token = (string) Helpers::setting('telegram_bot_token', '');
        if ($token === '') { return false; }
        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'chat_id'    => $chatId,
                'text'       => $text,
                'parse_mode' => 'Markdown',
            ]),
        ]);
        $res = curl_exec($ch);
        $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
        curl_close($ch);
        return $ok && $res !== false;
    }
}
