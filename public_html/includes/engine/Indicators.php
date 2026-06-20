<?php
declare(strict_types=1);

/**
 * Indicators - pure mathematical technical-analysis library.
 *
 * Every function operates on plain float arrays (oldest -> newest) and
 * returns either a single latest value or a full series. No external
 * services, no AI - just standard, well-known formulas.
 */
final class Indicators
{
    // ---------------- Moving Averages --------------------------------

    /** Simple Moving Average (returns full series, null until enough data). */
    public static function sma(array $values, int $period): array
    {
        $n = count($values);
        $out = array_fill(0, $n, null);
        if ($period <= 0 || $n < $period) {
            return $out;
        }
        $sum = array_sum(array_slice($values, 0, $period));
        $out[$period - 1] = $sum / $period;
        for ($i = $period; $i < $n; $i++) {
            $sum += $values[$i] - $values[$i - $period];
            $out[$i] = $sum / $period;
        }
        return $out;
    }

    /** Exponential Moving Average. */
    public static function ema(array $values, int $period): array
    {
        $n = count($values);
        $out = array_fill(0, $n, null);
        if ($period <= 0 || $n < $period) {
            return $out;
        }
        $k = 2 / ($period + 1);
        // seed with SMA of first period
        $seed = array_sum(array_slice($values, 0, $period)) / $period;
        $out[$period - 1] = $seed;
        $prev = $seed;
        for ($i = $period; $i < $n; $i++) {
            $prev = ($values[$i] - $prev) * $k + $prev;
            $out[$i] = $prev;
        }
        return $out;
    }

    public static function last(array $series): ?float
    {
        for ($i = count($series) - 1; $i >= 0; $i--) {
            if ($series[$i] !== null) {
                return (float) $series[$i];
            }
        }
        return null;
    }

    // ---------------- RSI (Wilder smoothing) -------------------------

    public static function rsi(array $close, int $period = 14): array
    {
        $n = count($close);
        $out = array_fill(0, $n, null);
        if ($n <= $period) {
            return $out;
        }
        $gain = 0.0;
        $loss = 0.0;
        for ($i = 1; $i <= $period; $i++) {
            $diff = $close[$i] - $close[$i - 1];
            if ($diff >= 0) { $gain += $diff; } else { $loss -= $diff; }
        }
        $avgGain = $gain / $period;
        $avgLoss = $loss / $period;
        $out[$period] = $avgLoss == 0.0 ? 100.0 : 100 - (100 / (1 + ($avgGain / $avgLoss)));
        for ($i = $period + 1; $i < $n; $i++) {
            $diff = $close[$i] - $close[$i - 1];
            $g = $diff >= 0 ? $diff : 0;
            $l = $diff < 0 ? -$diff : 0;
            $avgGain = (($avgGain * ($period - 1)) + $g) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $l) / $period;
            $out[$i] = $avgLoss == 0.0 ? 100.0 : 100 - (100 / (1 + ($avgGain / $avgLoss)));
        }
        return $out;
    }

    // ---------------- MACD -------------------------------------------

    /** @return array{macd:array,signal:array,hist:array} */
    public static function macd(array $close, int $fast = 12, int $slow = 26, int $signalP = 9): array
    {
        $emaFast = self::ema($close, $fast);
        $emaSlow = self::ema($close, $slow);
        $n = count($close);
        $macd = array_fill(0, $n, null);
        for ($i = 0; $i < $n; $i++) {
            if ($emaFast[$i] !== null && $emaSlow[$i] !== null) {
                $macd[$i] = $emaFast[$i] - $emaSlow[$i];
            }
        }
        // signal = EMA of macd (ignoring leading nulls)
        $compact = array_values(array_filter($macd, static fn ($x) => $x !== null));
        $sigCompact = self::ema($compact, $signalP);
        $signal = array_fill(0, $n, null);
        $offset = $n - count($compact);
        for ($i = 0; $i < count($sigCompact); $i++) {
            if ($sigCompact[$i] !== null) {
                $signal[$offset + $i] = $sigCompact[$i];
            }
        }
        $hist = array_fill(0, $n, null);
        for ($i = 0; $i < $n; $i++) {
            if ($macd[$i] !== null && $signal[$i] !== null) {
                $hist[$i] = $macd[$i] - $signal[$i];
            }
        }
        return ['macd' => $macd, 'signal' => $signal, 'hist' => $hist];
    }

    // ---------------- Bollinger Bands --------------------------------

    /** @return array{middle:array,upper:array,lower:array} */
    public static function bollinger(array $close, int $period = 20, float $mult = 2.0): array
    {
        $n = count($close);
        $mid = self::sma($close, $period);
        $upper = array_fill(0, $n, null);
        $lower = array_fill(0, $n, null);
        for ($i = $period - 1; $i < $n; $i++) {
            if ($mid[$i] === null) { continue; }
            $slice = array_slice($close, $i - $period + 1, $period);
            $mean = $mid[$i];
            $variance = 0.0;
            foreach ($slice as $v) { $variance += ($v - $mean) ** 2; }
            $sd = sqrt($variance / $period);
            $upper[$i] = $mean + $mult * $sd;
            $lower[$i] = $mean - $mult * $sd;
        }
        return ['middle' => $mid, 'upper' => $upper, 'lower' => $lower];
    }

    // ---------------- ATR --------------------------------------------

    public static function atr(array $high, array $low, array $close, int $period = 14): array
    {
        $n = count($close);
        $out = array_fill(0, $n, null);
        if ($n <= $period) { return $out; }
        $tr = [];
        for ($i = 0; $i < $n; $i++) {
            if ($i === 0) { $tr[$i] = $high[$i] - $low[$i]; continue; }
            $tr[$i] = max(
                $high[$i] - $low[$i],
                abs($high[$i] - $close[$i - 1]),
                abs($low[$i] - $close[$i - 1])
            );
        }
        $atr = array_sum(array_slice($tr, 1, $period)) / $period;
        $out[$period] = $atr;
        for ($i = $period + 1; $i < $n; $i++) {
            $atr = (($atr * ($period - 1)) + $tr[$i]) / $period;
            $out[$i] = $atr;
        }
        return $out;
    }

    // ---------------- VWAP -------------------------------------------

    /** Session VWAP across the supplied window (cumulative). */
    public static function vwap(array $high, array $low, array $close, array $volume): array
    {
        $n = count($close);
        $out = array_fill(0, $n, null);
        $cumPV = 0.0;
        $cumV  = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $tp = ($high[$i] + $low[$i] + $close[$i]) / 3;
            $cumPV += $tp * $volume[$i];
            $cumV  += $volume[$i];
            $out[$i] = $cumV > 0 ? $cumPV / $cumV : null;
        }
        return $out;
    }

    // ---------------- Stochastic RSI ---------------------------------

    /** @return array{k:array,d:array} */
    public static function stochRsi(array $close, int $rsiP = 14, int $stochP = 14, int $kS = 3, int $dS = 3): array
    {
        $rsi = self::rsi($close, $rsiP);
        $rsiVals = array_values(array_filter($rsi, static fn ($x) => $x !== null));
        $m = count($rsiVals);
        $stoch = [];
        for ($i = 0; $i < $m; $i++) {
            if ($i < $stochP - 1) { $stoch[$i] = null; continue; }
            $window = array_slice($rsiVals, $i - $stochP + 1, $stochP);
            $min = min($window); $max = max($window);
            $stoch[$i] = ($max - $min) == 0 ? 0 : (($rsiVals[$i] - $min) / ($max - $min)) * 100;
        }
        $kSeries = self::sma(array_map(static fn ($x) => $x ?? 0, $stoch), $kS);
        $dSeries = self::sma(array_map(static fn ($x) => $x ?? 0, $kSeries), $dS);
        return ['k' => $kSeries, 'd' => $dSeries];
    }

    // ---------------- OBV --------------------------------------------

    public static function obv(array $close, array $volume): array
    {
        $n = count($close);
        $out = array_fill(0, $n, 0.0);
        for ($i = 1; $i < $n; $i++) {
            if ($close[$i] > $close[$i - 1]) {
                $out[$i] = $out[$i - 1] + $volume[$i];
            } elseif ($close[$i] < $close[$i - 1]) {
                $out[$i] = $out[$i - 1] - $volume[$i];
            } else {
                $out[$i] = $out[$i - 1];
            }
        }
        return $out;
    }

    // ---------------- Volume Profile ---------------------------------

    /**
     * Build a volume profile over the price range, returning POC
     * (point of control) and value area high/low.
     *
     * @return array{poc:float,vah:float,val:float,bins:array}
     */
    public static function volumeProfile(array $high, array $low, array $close, array $volume, int $bins = 24): array
    {
        $n = count($close);
        if ($n === 0) {
            return ['poc' => 0, 'vah' => 0, 'val' => 0, 'bins' => []];
        }
        $min = min($low);
        $max = max($high);
        $range = max($max - $min, 1e-9);
        $step = $range / $bins;
        $buckets = array_fill(0, $bins, 0.0);
        for ($i = 0; $i < $n; $i++) {
            $tp = ($high[$i] + $low[$i] + $close[$i]) / 3;
            $idx = (int) min($bins - 1, max(0, floor(($tp - $min) / $step)));
            $buckets[$idx] += $volume[$i];
        }
        $pocIdx = 0; $pocVol = -1;
        foreach ($buckets as $i => $vol) {
            if ($vol > $pocVol) { $pocVol = $vol; $pocIdx = $i; }
        }
        $poc = $min + ($pocIdx + 0.5) * $step;

        // Value area = 70% of volume around POC
        $totalVol = array_sum($buckets);
        $target = $totalVol * 0.70;
        $acc = $buckets[$pocIdx];
        $lo = $pocIdx; $hi = $pocIdx;
        while ($acc < $target && ($lo > 0 || $hi < $bins - 1)) {
            $below = $lo > 0 ? $buckets[$lo - 1] : -1;
            $above = $hi < $bins - 1 ? $buckets[$hi + 1] : -1;
            if ($above >= $below) { $hi++; $acc += max($above, 0); }
            else { $lo--; $acc += max($below, 0); }
        }
        $val = $min + $lo * $step;
        $vah = $min + ($hi + 1) * $step;
        $profile = [];
        foreach ($buckets as $i => $vol) {
            $profile[] = ['price' => $min + ($i + 0.5) * $step, 'volume' => $vol];
        }
        return ['poc' => $poc, 'vah' => $vah, 'val' => $val, 'bins' => $profile];
    }

    // ---------------- Aggregate snapshot -----------------------------

    /**
     * Compute the full indicator snapshot (latest values) for an OHLCV set.
     * Returns a flat associative array used by the scanner and signal engine.
     */
    public static function snapshot(array $ohlcv): array
    {
        $c = $ohlcv['close']; $h = $ohlcv['high']; $l = $ohlcv['low']; $v = $ohlcv['volume'];
        if (count($c) < 50) {
            return ['ready' => false];
        }
        $macd = self::macd($c);
        $bb   = self::bollinger($c, 20, 2.0);
        $srsi = self::stochRsi($c);
        $vp   = self::volumeProfile($h, $l, $c, $v);
        $price = end($c);

        return [
            'ready'    => true,
            'price'    => (float) $price,
            'rsi'      => self::last(self::rsi($c, 14)),
            'ema20'    => self::last(self::ema($c, 20)),
            'ema50'    => self::last(self::ema($c, 50)),
            'ema100'   => self::last(self::ema($c, 100)),
            'ema200'   => self::last(self::ema($c, 200)),
            'sma20'    => self::last(self::sma($c, 20)),
            'sma50'    => self::last(self::sma($c, 50)),
            'vwap'     => self::last(self::vwap($h, $l, $c, $v)),
            'macd'     => self::last($macd['macd']),
            'macd_signal' => self::last($macd['signal']),
            'macd_hist'   => self::last($macd['hist']),
            'bb_upper' => self::last($bb['upper']),
            'bb_middle'=> self::last($bb['middle']),
            'bb_lower' => self::last($bb['lower']),
            'atr'      => self::last(self::atr($h, $l, $c, 14)),
            'stoch_k'  => self::last($srsi['k']),
            'stoch_d'  => self::last($srsi['d']),
            'obv'      => self::last(self::obv($c, $v)),
            'volume'   => (float) end($v),
            'vol_poc'  => $vp['poc'],
            'vol_vah'  => $vp['vah'],
            'vol_val'  => $vp['val'],
        ];
    }

    /**
     * Build a human-readable technical summary purely from the math.
     */
    public static function summary(array $s): array
    {
        if (empty($s['ready'])) {
            return ['bias' => 'insufficient_data', 'notes' => []];
        }
        $notes = [];
        $score = 0;

        if ($s['ema50'] && $s['ema200']) {
            if ($s['ema50'] > $s['ema200']) { $notes[] = 'EMA50 above EMA200 (bullish structure)'; $score += 2; }
            else { $notes[] = 'EMA50 below EMA200 (bearish structure)'; $score -= 2; }
        }
        if ($s['price'] && $s['ema20']) {
            if ($s['price'] > $s['ema20']) { $score += 1; } else { $score -= 1; }
        }
        if ($s['rsi'] !== null) {
            if ($s['rsi'] > 70) { $notes[] = 'RSI overbought (' . round($s['rsi'], 1) . ')'; $score -= 1; }
            elseif ($s['rsi'] < 30) { $notes[] = 'RSI oversold (' . round($s['rsi'], 1) . ')'; $score += 1; }
            elseif ($s['rsi'] > 50) { $score += 1; } else { $score -= 1; }
        }
        if ($s['macd_hist'] !== null) {
            if ($s['macd_hist'] > 0) { $notes[] = 'MACD histogram positive'; $score += 1; }
            else { $notes[] = 'MACD histogram negative'; $score -= 1; }
        }
        if ($s['vwap'] && $s['price']) {
            $notes[] = $s['price'] > $s['vwap'] ? 'Price above VWAP' : 'Price below VWAP';
        }

        $bias = match (true) {
            $score >= 3  => 'strong_bullish',
            $score >= 1  => 'bullish',
            $score <= -3 => 'strong_bearish',
            $score <= -1 => 'bearish',
            default      => 'neutral',
        };
        return ['bias' => $bias, 'score' => $score, 'notes' => $notes];
    }
}
