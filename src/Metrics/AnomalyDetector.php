<?php

declare(strict_types=1);

namespace Statisty\Metrics;

final class AnomalyDetector
{
    /**
     * Detect anomalies in a numeric series using z-score threshold.
     * Returns array of ['index' => int, 'label' => string, 'value' => float, 'z' => float]
     */
    public function detect(array $labels, array $values, float $threshold = 3.0): array
    {
        $clean = array_values(array_map(fn($v) => is_numeric($v) ? (float)$v : 0.0, $values));
        $n = count($clean);
        if ($n === 0) {
            return [];
        }

        $mean = array_sum($clean) / $n;
        $sumSq = 0.0;
        foreach ($clean as $v) {
            $sumSq += ($v - $mean) * ($v - $mean);
        }
        $std = $n > 1 ? sqrt($sumSq / ($n - 1)) : 0.0;

        // IQR based detection
        $sorted = $clean;
        sort($sorted);
        $q1 = $this->quantile($sorted, 0.25);
        $q3 = $this->quantile($sorted, 0.75);
        $iqr = $q3 - $q1;

        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $val = $clean[$i];
            $z = $std > 0 ? abs(($val - $mean) / $std) : 0.0;

            $isIqrOutlier = $iqr > 0 ? ($val > $q3 + 1.5 * $iqr || $val < $q1 - 1.5 * $iqr) : false;
            $isZ = $z >= $threshold;
            $isRatio = $mean > 0 ? ($val / $mean) >= 4.0 : false;

            $methods = [];
            if ($isIqrOutlier) {
                $methods[] = 'iqr';
            }
            if ($isZ) {
                $methods[] = 'zscore';
            }
            if ($isRatio) {
                $methods[] = 'ratio';
            }

            if ($methods !== []) {
                $out[] = [
                    'index' => $i,
                    'label' => $labels[$i] ?? (string) $i,
                    'value' => $val,
                    'z' => $z,
                    'methods' => $methods,
                ];
            }
        }

        return $out;
    }

    private function quantile(array $sorted, float $q): float
    {
        $n = count($sorted);
        if ($n === 0) {
            return 0.0;
        }
        $pos = ($n - 1) * $q;
        $lower = (int) floor($pos);
        $upper = (int) ceil($pos);
        if ($lower == $upper) {
            return $sorted[$lower];
        }
        $weight = $pos - $lower;
        return $sorted[$lower] * (1 - $weight) + $sorted[$upper] * $weight;
    }
}
