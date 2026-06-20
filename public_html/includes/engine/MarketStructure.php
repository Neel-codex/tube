<?php
declare(strict_types=1);

/**
 * MarketStructure - swing detection, HH/HL/LH/LL labelling,
 * trend classification and a numeric trend score.
 *
 * Uses fractal/pivot swing detection (a swing high is a candle whose
 * high is the highest within +/- `lookback` bars).
 */
final class MarketStructure
{
    /**
     * Detect swing highs and lows.
     * @return array{highs:array<int,array{i:int,price:float}>,lows:array<int,array{i:int,price:float}>}
     */
    public static function swings(array $high, array $low, int $lookback = 3): array
    {
        $n = count($high);
        $highs = [];
        $lows = [];
        for ($i = $lookback; $i < $n - $lookback; $i++) {
            $isHigh = true;
            $isLow = true;
            for ($j = $i - $lookback; $j <= $i + $lookback; $j++) {
                if ($j === $i) { continue; }
                if ($high[$j] >= $high[$i]) { $isHigh = false; }
                if ($low[$j] <= $low[$i]) { $isLow = false; }
            }
            if ($isHigh) { $highs[] = ['i' => $i, 'price' => (float) $high[$i]]; }
            if ($isLow)  { $lows[]  = ['i' => $i, 'price' => (float) $low[$i]]; }
        }
        return ['highs' => $highs, 'lows' => $lows];
    }

    /**
     * Label the most recent structure points as HH/HL/LH/LL and
     * classify the trend.
     *
     * @return array{
     *   trend:string, labels:array, trend_score:float,
     *   last_high:?float, last_low:?float
     * }
     */
    public static function analyze(array $high, array $low, array $close, int $lookback = 3): array
    {
        $sw = self::swings($high, $low, $lookback);
        $highs = $sw['highs'];
        $lows  = $sw['lows'];
        $labels = [];

        // Label swing highs
        for ($k = 1; $k < count($highs); $k++) {
            $labels[] = [
                'i'     => $highs[$k]['i'],
                'price' => $highs[$k]['price'],
                'type'  => $highs[$k]['price'] > $highs[$k - 1]['price'] ? 'HH' : 'LH',
            ];
        }
        // Label swing lows
        for ($k = 1; $k < count($lows); $k++) {
            $labels[] = [
                'i'     => $lows[$k]['i'],
                'price' => $lows[$k]['price'],
                'type'  => $lows[$k]['price'] > $lows[$k - 1]['price'] ? 'HL' : 'LL',
            ];
        }
        usort($labels, static fn ($a, $b) => $a['i'] <=> $b['i']);

        // Count recent labels (last 6 structure points)
        $recent = array_slice($labels, -6);
        $hh = $hl = $lh = $ll = 0;
        foreach ($recent as $r) {
            match ($r['type']) {
                'HH' => $hh++,
                'HL' => $hl++,
                'LH' => $lh++,
                'LL' => $ll++,
                default => null,
            };
        }

        $bull = $hh + $hl;
        $bear = $lh + $ll;
        $total = max($bull + $bear, 1);

        // Slope of close via linear regression on last 20 closes
        $slope = self::slope(array_slice($close, -20));

        $trend = 'consolidation';
        if ($bull > $bear && $slope > 0) { $trend = 'uptrend'; }
        elseif ($bear > $bull && $slope < 0) { $trend = 'downtrend'; }

        // trend_score in [-100, 100]
        $structureScore = (($bull - $bear) / $total) * 60;
        $slopeScore = max(-40, min(40, $slope * 40));
        $trendScore = round($structureScore + $slopeScore, 2);

        return [
            'trend'        => $trend,
            'labels'       => $labels,
            'recent'       => $recent,
            'counts'       => ['HH' => $hh, 'HL' => $hl, 'LH' => $lh, 'LL' => $ll],
            'trend_score'  => $trendScore,
            'last_high'    => $highs ? end($highs)['price'] : null,
            'last_low'     => $lows ? end($lows)['price'] : null,
            'slope'        => round($slope, 6),
        ];
    }

    /** Normalised slope of a price series (per-bar % change via regression). */
    public static function slope(array $values): float
    {
        $n = count($values);
        if ($n < 2) { return 0.0; }
        $sumX = $sumY = $sumXY = $sumXX = 0.0;
        foreach ($values as $i => $y) {
            $sumX += $i; $sumY += $y; $sumXY += $i * $y; $sumXX += $i * $i;
        }
        $denom = ($n * $sumXX - $sumX * $sumX);
        if ($denom == 0.0) { return 0.0; }
        $m = ($n * $sumXY - $sumX * $sumY) / $denom;
        $avg = $sumY / $n;
        return $avg != 0.0 ? ($m / $avg) * 100 : 0.0; // % of price per bar
    }
}
