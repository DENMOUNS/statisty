<?php

require __DIR__ . '/../vendor/autoload.php';

// Provide compatibility alias for legacy test namespace
if (! class_exists(\Statisty\Tests\TestCase::class) && class_exists(\Tests\TestCase::class)) {
    class_alias(\Tests\TestCase::class, \Statisty\Tests\TestCase::class);
}
