<?php
declare(strict_types=1);

/**
 * SignalEngine - generates trade signals only when MULTIPLE confirmations
 * align. The confidence score is a transparent weighted sum:
 *
 *   Trend          25%
 *   Volume         20%
 *   RSI            15%
 *   Structure      20%
 *   Supply/Demand  20%   (total 100)
 *
 * Weights are configurable in the `settings` table. A signal is only
 * emitted when confidence >= signal_min_confidence (default 60).
 *
 * Entry / SL / TP are derived from ATR + nearest S/D zones (no AI).
 */
final class SignalEngine
{
    /** Generate (and persist) signals from a completed scanner analysis. */
    public static function fromAnalysis(array $a, string $style = 'intraday'): ?array
    {
        $s = $a['snapshot'];
        $minConf = (float) Helpers::setting('signal_min_confidence', 60);

        $cc          = self::computeConfluences($a);
        $confluences = $cc['confluences'];
        $price       = $cc['price'];
        $nearest     = $cc['nearest'];

        // Aggregate
        $net = 0.0;
        foreach ($confluences as $comp) { $net += $comp['signed']; }
        $direction = $net >= 0 ? 'long' : 'short';

        // Confidence = sum of |aligned| components that agree with direction
        $confidence = 0.0;
        foreach ($confluences as $comp) {
            if (($direction === 'long' && $comp['signed'] > 0) ||
                ($direction === 'short' && $comp['signed'] < 0)) {
                $confidence += abs($comp['signed']);
            }
        }
        $confidence = round(min(100, $confidence), 2);

        // Require at least 3 aligned confirmations and min confidence
        $aligned = 0;
        foreach ($confluences as $comp) {
            if (($direction === 'long' && $comp['signed'] > 0) ||
                ($direction === 'short' && $comp['signed'] < 0)) {
                $aligned++;
            }
        }
        if ($aligned < 3 || $confidence < $minConf) {
            return null;
        }

        // --- Risk levels from ATR + zones ---
        $atr = $s['atr'] ?: ($price * 0.01);
        $levels = self::levels($direction, $price, $atr, $nearest, $style);

        $signal = [
            'symbol'      => $a['symbol'],
            'timeframe'   => $a['timeframe'],
            'style'       => $style,
            'direction'   => $direction,
            'entry'       => $levels['entry'],
            'stop_loss'   => $levels['sl'],
            'tp1'         => $levels['tp1'],
            'tp2'         => $levels['tp2'],
            'tp3'         => $levels['tp3'],
            'risk_reward' => $levels['rr'],
            'confidence'  => $confidence,
            'confluences' => $confluences,
        ];
        return $signal;
    }

    /** Persist a signal row; returns insert id. */
    public static function persist(array $sig, bool $premium = false): int
    {
        return Database::instance()->insert('signals', [
            'symbol'      => $sig['symbol'],
            'timeframe'   => $sig['timeframe'],
            'style'       => $sig['style'],
            'direction'   => $sig['direction'],
            'entry'       => $sig['entry'],
            'stop_loss'   => $sig['stop_loss'],
            'tp1'         => $sig['tp1'],
            'tp2'         => $sig['tp2'],
            'tp3'         => $sig['tp3'],
            'risk_reward' => $sig['risk_reward'],
            'confidence'  => $sig['confidence'],
            'confluences' => json_encode($sig['confluences']),
            'status'      => 'active',
            'is_premium'  => $premium ? 1 : 0,
        ]);
    }

    /** Compute entry/SL/TP based on direction, ATR, nearest zones and style. */
    private static function levels(string $dir, float $price, float $atr, array $nearest, string $style): array
    {
        // ATR multiplier varies by style
        $slMult = match ($style) {
            'scalping' => 1.0,
            'swing'    => 2.5,
            default    => 1.5,   // intraday
        };
        $entry = $price;
        if ($dir === 'long') {
            $sl = $entry - $atr * $slMult;
            // tighten SL to demand zone low if close & valid
            if ($nearest['demand'] && $nearest['demand']['low'] < $entry) {
                $sl = max($sl, min($sl + $atr, $nearest['demand']['low'] - $atr * 0.2));
                $sl = min($sl, $entry - $atr * 0.5);
            }
            $risk = max($entry - $sl, $atr * 0.5);
            $tp1 = $entry + $risk * 1.0;
            $tp2 = $entry + $risk * 2.0;
            $tp3 = $entry + $risk * 3.0;
        } else {
            $sl = $entry + $atr * $slMult;
            if ($nearest['supply'] && $nearest['supply']['high'] > $entry) {
                $sl = min($sl, max($sl - $atr, $nearest['supply']['high'] + $atr * 0.2));
                $sl = max($sl, $entry + $atr * 0.5);
            }
            $risk = max($sl - $entry, $atr * 0.5);
            $tp1 = $entry - $risk * 1.0;
            $tp2 = $entry - $risk * 2.0;
            $tp3 = $entry - $risk * 3.0;
        }
        $reward = abs($tp2 - $entry);
        $rr = $risk > 0 ? round($reward / $risk, 2) : 0;

        return [
            'entry' => round($entry, 8),
            'sl'    => round($sl, 8),
            'tp1'   => round($tp1, 8),
            'tp2'   => round($tp2, 8),
            'tp3'   => round($tp3, 8),
            'rr'    => $rr,
        ];
    }

    /**
     * Compute the five weighted confluence components for an analysis.
     * Shared by fromAnalysis() (gated) and instant() (always returns).
     */
    private static function computeConfluences(array $a): array
    {
        $s      = $a['snapshot'];
        $struct = $a['structure'];
        $smc    = $a['smc'];
        $zones  = $a['zones'];
        $weights = self::weights();
        $confluences = [];

        // Trend
        if ($struct['trend'] === 'uptrend')        { $trendComp = 1.0; }
        elseif ($struct['trend'] === 'downtrend')  { $trendComp = -1.0; }
        else { $trendComp = max(-0.5, min(0.5, $struct['trend_score'] / 100)); }
        $confluences['trend'] = self::component($weights['trend'], $trendComp);

        // Volume
        $volComp = 0.0;
        if ($s['obv'] !== null) { $volComp = ($s['macd_hist'] ?? 0) >= 0 ? 0.6 : -0.6; }
        $volComp += ($s['volume'] > 0) ? ($trendComp >= 0 ? 0.2 : -0.2) : 0;
        $confluences['volume'] = self::component($weights['volume'], max(-1, min(1, $volComp)));

        // RSI
        $rsiComp = 0.0;
        if ($s['rsi'] !== null) {
            if ($s['rsi'] < 30)     { $rsiComp = 0.8; }
            elseif ($s['rsi'] > 70) { $rsiComp = -0.8; }
            else { $rsiComp = ($s['rsi'] - 50) / 50; }
        }
        $confluences['rsi'] = self::component($weights['rsi'], $rsiComp);

        // Structure
        $structComp = max(-1, min(1, $struct['trend_score'] / 100));
        foreach ($smc['bos_choch'] ?? [] as $e) {
            $structComp += ($e['direction'] === 'bullish') ? 0.3 : -0.3;
        }
        $confluences['structure'] = self::component($weights['structure'], max(-1, min(1, $structComp)));

        // Supply / Demand
        $price = $s['price'];
        $nearest = SupplyDemand::nearest($zones, $price);
        $sdComp = 0.0;
        if ($nearest['demand']) {
            $d = abs($price - (($nearest['demand']['high'] + $nearest['demand']['low']) / 2)) / max($price, 1e-9);
            if ($d < 0.02) { $sdComp += 0.7; }
        }
        if ($nearest['supply']) {
            $d = abs((($nearest['supply']['high'] + $nearest['supply']['low']) / 2) - $price) / max($price, 1e-9);
            if ($d < 0.02) { $sdComp -= 0.7; }
        }
        if (($smc['premium_discount']['zone'] ?? '') === 'discount') { $sdComp += 0.3; }
        elseif (($smc['premium_discount']['zone'] ?? '') === 'premium') { $sdComp -= 0.3; }
        $confluences['supply_demand'] = self::component($weights['supply_demand'], max(-1, min(1, $sdComp)));

        return ['confluences' => $confluences, 'price' => $price, 'nearest' => $nearest];
    }

    /**
     * INSTANT / AUTO signal - always returns a best-effort signal (never null),
     * for the "submit a chart, get a signal" auto-analyzer.
     *
     * Optionally folds in a rule-based ChartReader bias as a 6th confluence.
     *
     * @param array      $a         analysis bundle (snapshot/structure/zones/smc/symbol/timeframe)
     * @param string     $style     scalping|intraday|swing
     * @param array|null $chartRead optional ChartReader::analyze() result
     */
    public static function instant(array $a, string $style = 'intraday', ?array $chartRead = null): array
    {
        $cc          = self::computeConfluences($a);
        $confluences = $cc['confluences'];
        $price       = $cc['price'];
        $nearest     = $cc['nearest'];
        $s           = $a['snapshot'];

        // Optional chart-read confluence (weight 15, on top; normalised later)
        if ($chartRead && ($chartRead['ok'] ?? false)) {
            $confluences['chart_read'] = self::component(15, (float) ($chartRead['bias_factor'] ?? 0));
        }

        $net = 0.0;
        foreach ($confluences as $comp) { $net += $comp['signed']; }

        // Direction: net lean; tie-break with structure trend, then chart bias.
        if (abs($net) < 0.01) {
            $net = ($a['structure']['trend_score'] ?? 0) <=> 0;
            if ($net === 0 && $chartRead) { $net = (float) ($chartRead['bias_factor'] ?? 0); }
        }
        $direction = $net >= 0 ? 'long' : 'short';

        // Confidence = aligned component magnitude + aligned count of confirmations
        $confidence = 0.0; $aligned = 0;
        foreach ($confluences as $comp) {
            $agrees = ($direction === 'long' && $comp['signed'] > 0) || ($direction === 'short' && $comp['signed'] < 0);
            if ($agrees) { $confidence += abs($comp['signed']); $aligned++; }
        }
        $confidence = round(min(100, $confidence), 2);

        $atr = $s['atr'] ?: ($price * 0.01);
        $levels = self::levels($direction, $price, $atr, $nearest, $style);

        return [
            'symbol'      => $a['symbol'] ?? '',
            'timeframe'   => $a['timeframe'] ?? '',
            'style'       => $style,
            'direction'   => $direction,
            'entry'       => $levels['entry'],
            'stop_loss'   => $levels['sl'],
            'tp1'         => $levels['tp1'],
            'tp2'         => $levels['tp2'],
            'tp3'         => $levels['tp3'],
            'risk_reward' => $levels['rr'],
            'confidence'  => $confidence,
            'aligned'     => $aligned,
            'grade'       => $confidence >= 75 ? 'A' : ($confidence >= 60 ? 'B' : ($confidence >= 45 ? 'C' : 'D')),
            'confluences' => $confluences,
            'on_demand'   => true,
        ];
    }

    /** A weighted component: signed contribution + raw weight. */
    private static function component(float $weight, float $factor): array
    {
        return [
            'weight' => $weight,
            'factor' => round($factor, 3),
            'signed' => round($weight * $factor, 3),
        ];
    }

    /** Load configurable weights (sum normalised to 100). */
    private static function weights(): array
    {
        $w = [
            'trend'         => (float) Helpers::setting('weight_trend', 25),
            'volume'        => (float) Helpers::setting('weight_volume', 20),
            'rsi'           => (float) Helpers::setting('weight_rsi', 15),
            'structure'     => (float) Helpers::setting('weight_structure', 20),
            'supply_demand' => (float) Helpers::setting('weight_supply_demand', 20),
        ];
        $sum = array_sum($w);
        if ($sum > 0 && abs($sum - 100) > 0.01) {
            foreach ($w as $k => $v) { $w[$k] = $v / $sum * 100; }
        }
        return $w;
    }

    /**
     * Generate signals for all three styles from one analysis,
     * persisting any that pass. Returns the persisted signals.
     */
    public static function generateAll(array $analysis): array
    {
        $out = [];
        foreach (['scalping', 'intraday', 'swing'] as $style) {
            $sig = self::fromAnalysis($analysis, $style);
            if ($sig !== null) {
                $premium = $sig['confidence'] >= 80;
                $sig['id'] = self::persist($sig, $premium);
                $out[] = $sig;
            }
        }
        return $out;
    }
}
