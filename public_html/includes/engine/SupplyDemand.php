<?php
declare(strict_types=1);

/**
 * SupplyDemand - rule-based supply/demand zone detection.
 *
 * A demand zone = a base (small-range consolidation) followed by a strong
 * bullish impulse (rally). A supply zone = base followed by a strong
 * bearish impulse (drop). Zones are rated by impulse strength and how
 * recently they formed, and flagged fresh / tested / mitigated.
 */
final class SupplyDemand
{
    /**
     * @return array<int,array{type:string,high:float,low:float,strength:float,index:int,status:string}>
     */
    public static function detect(array $high, array $low, array $close, array $open, int $maxZones = 8): array
    {
        $n = count($close);
        if ($n < 20) { return []; }

        // average candle body for impulse comparison
        $bodies = [];
        for ($i = 0; $i < $n; $i++) {
            $bodies[] = abs($close[$i] - $open[$i]);
        }
        $avgBody = array_sum($bodies) / max(count($bodies), 1);
        $price = (float) end($close);
        $zones = [];

        // Scan for base + impulse patterns
        for ($i = 2; $i < $n - 2; $i++) {
            $body = $bodies[$i];
            // impulse candle: body >= 1.8x average
            if ($body < $avgBody * 1.8) { continue; }
            $bullish = $close[$i] > $open[$i];

            // base = previous 1-3 small candles
            $baseHigh = max($high[$i - 1], $high[$i - 2]);
            $baseLow  = min($low[$i - 1], $low[$i - 2]);
            $baseRange = $baseHigh - $baseLow;
            if ($baseRange <= 0) { continue; }

            // strength: impulse size relative to base + recency
            $strength = min(100, ($body / max($avgBody, 1e-9)) * 22 + (($i / $n) * 25));

            if ($bullish) {
                // demand zone at the base below
                $zHigh = $baseHigh;
                $zLow  = $baseLow;
                $type  = 'demand';
            } else {
                $zHigh = $baseHigh;
                $zLow  = $baseLow;
                $type  = 'supply';
            }

            // determine status: has price returned into the zone after formation?
            $status = 'fresh';
            for ($j = $i + 1; $j < $n; $j++) {
                if ($low[$j] <= $zHigh && $high[$j] >= $zLow) {
                    $status = 'tested';
                    // mitigated if closed beyond the zone
                    if (($type === 'demand' && $close[$j] < $zLow) ||
                        ($type === 'supply' && $close[$j] > $zHigh)) {
                        $status = 'mitigated';
                        break;
                    }
                }
            }

            $zones[] = [
                'type'     => $type,
                'high'     => round($zHigh, 8),
                'low'      => round($zLow, 8),
                'strength' => round($strength, 2),
                'index'    => $i,
                'status'   => $status,
                'distance_pct' => $price > 0 ? round(((($zHigh + $zLow) / 2) - $price) / $price * 100, 2) : 0,
            ];
        }

        // Keep strongest, most recent, non-mitigated first
        usort($zones, static function ($a, $b) {
            if ($a['status'] === 'mitigated' && $b['status'] !== 'mitigated') return 1;
            if ($b['status'] === 'mitigated' && $a['status'] !== 'mitigated') return -1;
            return $b['strength'] <=> $a['strength'];
        });

        return array_slice($zones, 0, $maxZones);
    }

    /**
     * Nearest fresh demand (support) and supply (resistance) to current price.
     */
    public static function nearest(array $zones, float $price): array
    {
        $demand = null; $supply = null;
        foreach ($zones as $z) {
            if ($z['status'] === 'mitigated') { continue; }
            $mid = ($z['high'] + $z['low']) / 2;
            if ($z['type'] === 'demand' && $mid <= $price) {
                if ($demand === null || $mid > ($demand['high'] + $demand['low']) / 2) { $demand = $z; }
            }
            if ($z['type'] === 'supply' && $mid >= $price) {
                if ($supply === null || $mid < ($supply['high'] + $supply['low']) / 2) { $supply = $z; }
            }
        }
        return ['demand' => $demand, 'supply' => $supply];
    }
}
