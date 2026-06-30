<?php

declare(strict_types=1);

namespace Statisty\Tests\Unit;

use Statisty\Metrics\AnomalyDetector;
use Statisty\Tests\TestCase;

final class AnomalyDetectorTest extends TestCase
{
    public function test_detect_identifies_outliers(): void
    {
        $detector = new AnomalyDetector();

        $labels = ['a','b','c','d'];
        $values = [10, 12, 11, 1000]; // last value is an obvious outlier

        $out = $detector->detect($labels, $values, 3.0);

        $this->assertIsArray($out);
        $this->assertNotEmpty($out);
        $this->assertSame('d', $out[0]['label']);
        $this->assertNotEmpty($out[0]['methods']);
    }
}
