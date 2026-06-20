<?php
declare(strict_types=1);

/**
 * SMC - Smart Money Concepts detection (rule-based, mathematical).
 *
 * Detects:
 *   - BOS  (Break of Structure)
 *   - CHOCH (Change of Character)
 *   - Liquidity Sweeps
 *   - Equal Highs / Equal Lows
 *   - Fair Value Gaps (FVG / imbalances)
 *   - Order Blocks (last opposing candle before impulse)
 *   - Breaker Blocks (failed order block flipped)
 *   - Premium / Discount zones (relative to dealing range)
 *
 * All purely from price geometry - no AI.
 */
final class SMC
{
    public static function analyze(array $ohlcv, int $lookback = 3): array
    {
        $h = $ohlcv['high']; $l = $ohlcv['low']; $c = $ohlcv['close']; $o = $ohlcv['open'];
        $n = count($c);
        if ($n < 30) {
            return ['ready' => false];
        }

        $swings = MarketStructure::swings($h, $l, $lookback);
        $struct = MarketStructure::analyze($h, $l, $c, $lookback);

        return [
            'ready'           => true,
            'bos_choch'       => self::bosChoch($swings, $c),
            'liquidity_sweeps'=> self::liquiditySweeps($h, $l, $c, $swings),
            'equal_levels'    => self::equalLevels($swings),
            'fvg'             => self::fairValueGaps($h, $l, $n),
            'order_blocks'    => self::orderBlocks($o, $h, $l, $c),
            'breaker_blocks'  => self::breakerBlocks($o, $h, $l, $c),
            'premium_discount'=> self::premiumDiscount($h, $l, $c),
            'structure'       => $struct,
        ];
    }

    /** BOS = price breaks last swing in trend direction; CHOCH = breaks against trend. */
    private static function bosChoch(array $swings, array $close): array
    {
        $events = [];
        $price = (float) end($close);
        $highs = $swings['highs'];
        $lows  = $swings['lows'];
        if (count($highs) >= 2) {
            $lastHigh = end($highs)['price'];
            if ($price > $lastHigh) {
                $events[] = ['type' => 'BOS', 'direction' => 'bullish', 'level' => round($lastHigh, 8)];
            }
        }
        if (count($lows) >= 2) {
            $lastLow = end($lows)['price'];
            if ($price < $lastLow) {
                $events[] = ['type' => 'BOS', 'direction' => 'bearish', 'level' => round($lastLow, 8)];
            }
        }
        // CHOCH: a bullish break after a sequence of lower lows, or vice versa
        if (count($highs) >= 2 && count($lows) >= 2) {
            $h0 = $highs[count($highs) - 2]['price'] ?? null;
            $h1 = end($highs)['price'];
            $l0 = $lows[count($lows) - 2]['price'] ?? null;
            $l1 = end($lows)['price'];
            if ($h0 !== null && $l0 !== null) {
                if ($l1 < $l0 && $price > $h1) {
                    $events[] = ['type' => 'CHOCH', 'direction' => 'bullish', 'level' => round($h1, 8)];
                }
                if ($h1 > $h0 && $price < $l1) {
                    $events[] = ['type' => 'CHOCH', 'direction' => 'bearish', 'level' => round($l1, 8)];
                }
            }
        }
        return $events;
    }

    /** Liquidity sweep: wick pierces a prior swing then closes back inside. */
    private static function liquiditySweeps(array $high, array $low, array $close, array $swings): array
    {
        $sweeps = [];
        $n = count($close);
        $lastIdx = $n - 1;
        foreach ($swings['highs'] as $sh) {
            if ($sh['i'] >= $lastIdx - 5 && $sh['i'] < $lastIdx) {
                if ($high[$lastIdx] > $sh['price'] && $close[$lastIdx] < $sh['price']) {
                    $sweeps[] = ['type' => 'sell_side_sweep', 'level' => round($sh['price'], 8)];
                }
            }
        }
        foreach ($swings['lows'] as $sl) {
            if ($sl['i'] >= $lastIdx - 5 && $sl['i'] < $lastIdx) {
                if ($low[$lastIdx] < $sl['price'] && $close[$lastIdx] > $sl['price']) {
                    $sweeps[] = ['type' => 'buy_side_sweep', 'level' => round($sl['price'], 8)];
                }
            }
        }
        return $sweeps;
    }

    /** Equal highs / equal lows within a small tolerance. */
    private static function equalLevels(array $swings, float $tol = 0.001): array
    {
        $eq = ['equal_highs' => [], 'equal_lows' => []];
        $highs = array_map(static fn ($x) => $x['price'], $swings['highs']);
        $lows  = array_map(static fn ($x) => $x['price'], $swings['lows']);
        $scan = static function (array $arr) use ($tol): array {
            $res = [];
            $count = count($arr);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($arr[$i] > 0 && abs($arr[$i] - $arr[$j]) / $arr[$i] <= $tol) {
                        $res[] = round(($arr[$i] + $arr[$j]) / 2, 8);
                    }
                }
            }
            return array_values(array_unique($res));
        };
        $eq['equal_highs'] = array_slice($scan($highs), -3);
        $eq['equal_lows']  = array_slice($scan($lows), -3);
        return $eq;
    }

    /** Fair Value Gaps: 3-candle imbalance where candle1.high < candle3.low (bullish) etc. */
    private static function fairValueGaps(array $high, array $low, int $n): array
    {
        $fvg = [];
        $start = max(1, $n - 40);
        for ($i = $start; $i < $n - 1; $i++) {
            // bullish FVG: gap between high[i-1] and low[i+1]
            if ($high[$i - 1] < $low[$i + 1]) {
                $fvg[] = ['type' => 'bullish', 'low' => round($high[$i - 1], 8), 'high' => round($low[$i + 1], 8), 'index' => $i];
            }
            // bearish FVG
            if ($low[$i - 1] > $high[$i + 1]) {
                $fvg[] = ['type' => 'bearish', 'high' => round($low[$i - 1], 8), 'low' => round($high[$i + 1], 8), 'index' => $i];
            }
        }
        return array_slice($fvg, -6);
    }

    /** Order Blocks: last down-candle before a strong up-move (bullish OB) and vice versa. */
    private static function orderBlocks(array $open, array $high, array $low, array $close): array
    {
        $n = count($close);
        $obs = [];
        $bodies = [];
        for ($i = 0; $i < $n; $i++) { $bodies[] = abs($close[$i] - $open[$i]); }
        $avg = array_sum($bodies) / max($n, 1);
        for ($i = max(1, $n - 50); $i < $n - 1; $i++) {
            $impulse = $bodies[$i + 1];
            if ($impulse < $avg * 1.8) { continue; }
            $upImpulse = $close[$i + 1] > $open[$i + 1];
            $downCandle = $close[$i] < $open[$i];
            if ($upImpulse && $downCandle) {
                $obs[] = ['type' => 'bullish', 'high' => round($high[$i], 8), 'low' => round($low[$i], 8), 'index' => $i];
            }
            if (!$upImpulse && !$downCandle) {
                $obs[] = ['type' => 'bearish', 'high' => round($high[$i], 8), 'low' => round($low[$i], 8), 'index' => $i];
            }
        }
        return array_slice($obs, -5);
    }

    /** Breaker Block: an order block whose level has been violated and now acts inversely. */
    private static function breakerBlocks(array $open, array $high, array $low, array $close): array
    {
        $obs = self::orderBlocks($open, $high, $low, $close);
        $price = (float) end($close);
        $breakers = [];
        foreach ($obs as $ob) {
            if ($ob['type'] === 'bullish' && $price < $ob['low']) {
                $breakers[] = ['type' => 'bearish_breaker', 'high' => $ob['high'], 'low' => $ob['low']];
            }
            if ($ob['type'] === 'bearish' && $price > $ob['high']) {
                $breakers[] = ['type' => 'bullish_breaker', 'high' => $ob['high'], 'low' => $ob['low']];
            }
        }
        return $breakers;
    }

    /** Premium / Discount relative to the recent dealing range. */
    private static function premiumDiscount(array $high, array $low, array $close): array
    {
        $window = 50;
        $h = array_slice($high, -$window);
        $l = array_slice($low, -$window);
        $rangeHigh = max($h);
        $rangeLow  = min($l);
        $eq = ($rangeHigh + $rangeLow) / 2;        // equilibrium (50%)
        $price = (float) end($close);
        $range = max($rangeHigh - $rangeLow, 1e-9);
        $pct = ($price - $rangeLow) / $range * 100;
        $zone = $price > $eq ? 'premium' : 'discount';
        return [
            'zone'        => $zone,
            'equilibrium' => round($eq, 8),
            'range_high'  => round($rangeHigh, 8),
            'range_low'   => round($rangeLow, 8),
            'position_pct'=> round($pct, 2),
        ];
    }
}
