<?php
declare(strict_types=1);

/**
 * ChartReader - rule-based chart screenshot reader (NO AI / NO ML).
 *
 * Uses the GD library to analyse raw pixels of an uploaded chart image:
 *   - counts bullish (green/teal) vs bearish (red) candle pixels
 *   - estimates the on-screen trend by comparing the vertical position of
 *     candle pixels on the left vs the right of the chart
 *
 * This is pure pixel mathematics - it does not "understand" the image,
 * it measures colour balance and geometry. It is used only as a
 * supplementary cross-check; the actual signal is built from live
 * OHLCV market data.
 */
final class ChartReader
{
    /** Try to extract a trading symbol from an uploaded filename. */
    public static function symbolFromFilename(string $name): ?string
    {
        $name = strtoupper($name);
        // TradingView exports often look like BINANCE_BTCUSDT_60_xxxx.png or BTCUSDT_2024...
        if (preg_match('/([A-Z]{2,15}(?:USDT|USD|BTC|ETH|PERP))/', $name, $m)) {
            return $m[1];
        }
        if (preg_match('/\b([A-Z]{3,6}USDT)\b/', $name, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Analyse a chart image file. Returns a read result or ['ok'=>false].
     */
    public static function analyze(string $filePath): array
    {
        if (!function_exists('imagecreatefromstring') || !is_file($filePath)) {
            return ['ok' => false, 'reason' => 'GD unavailable or file missing'];
        }
        $raw = @file_get_contents($filePath);
        $img = $raw ? @imagecreatefromstring($raw) : false;
        if (!$img) {
            return ['ok' => false, 'reason' => 'Unsupported or corrupt image'];
        }
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w < 40 || $h < 40) {
            imagedestroy($img);
            return ['ok' => false, 'reason' => 'Image too small'];
        }


        // Sample at most ~200x200 points for performance
        $stepX = max(1, (int) floor($w / 200));
        $stepY = max(1, (int) floor($h / 200));

        $green = 0; $red = 0;
        // per-column accumulation of candle-pixel Y positions, split into thirds
        $thirds = [0 => ['sumY' => 0.0, 'n' => 0], 1 => ['sumY' => 0.0, 'n' => 0], 2 => ['sumY' => 0.0, 'n' => 0]];

        for ($x = 0; $x < $w; $x += $stepX) {
            $third = $x < $w / 3 ? 0 : ($x < 2 * $w / 3 ? 1 : 2);
            for ($y = 0; $y < $h; $y += $stepY) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $isGreen = ($g - $r > 25) && ($g - $b > 5);                 // green / teal up-candles
                $isRed   = ($r - $g > 25) && ($r - $b > 15);                // red down-candles
                if ($isGreen) {
                    $green++;
                    $thirds[$third]['sumY'] += $y; $thirds[$third]['n']++;
                } elseif ($isRed) {
                    $red++;
                    $thirds[$third]['sumY'] += $y; $thirds[$third]['n']++;
                }
            }
        }
        imagedestroy($img);

        $colored = $green + $red;
        if ($colored < 30) {
            return ['ok' => false, 'reason' => 'No clear candles detected (chart theme not recognised)'];
        }

        $greenPct = round($green / $colored * 100, 1);
        $redPct   = round($red / $colored * 100, 1);

        // Trend from candle Y-centroid: smaller Y = higher on screen = higher price.
        $leftC  = $thirds[0]['n'] ? $thirds[0]['sumY'] / $thirds[0]['n'] : null;
        $rightC = $thirds[2]['n'] ? $thirds[2]['sumY'] / $thirds[2]['n'] : null;
        $slopeDir = 'flat';
        $slopeStrength = 0.0;
        if ($leftC !== null && $rightC !== null) {
            $delta = $leftC - $rightC;                 // >0 means right is higher on screen => uptrend
            $slopeStrength = round(abs($delta) / $h * 100, 1);
            if ($delta > $h * 0.04) { $slopeDir = 'up'; }
            elseif ($delta < -$h * 0.04) { $slopeDir = 'down'; }
        }


        // Combine colour balance + slope into a directional bias.
        $score = 0.0;
        $score += ($greenPct - $redPct) / 100;                 // [-1,1] colour lean
        $score += $slopeDir === 'up' ? 0.5 : ($slopeDir === 'down' ? -0.5 : 0);
        $score = max(-1.5, min(1.5, $score));

        $bias = 'neutral';
        if ($score > 0.25) { $bias = 'bullish'; }
        elseif ($score < -0.25) { $bias = 'bearish'; }

        // Read confidence: stronger when colour balance is lopsided and slope is clear.
        $readConfidence = (int) round(
            min(100, abs($greenPct - $redPct) * 0.8 + $slopeStrength * 1.2 + min($colored, 400) / 8)
        );

        return [
            'ok'              => true,
            'bias'            => $bias,
            'bias_factor'     => round($score / 1.5, 3),   // normalised [-1,1] for the signal engine
            'green_pct'       => $greenPct,
            'red_pct'         => $redPct,
            'trend_direction' => $slopeDir,
            'trend_strength'  => $slopeStrength,
            'candle_pixels'   => $colored,
            'read_confidence' => $readConfidence,
            'notes'           => sprintf(
                'Detected %s%% bullish vs %s%% bearish candle colour with a %s on-screen trend.',
                $greenPct, $redPct, $slopeDir
            ),
        ];
    }
}
