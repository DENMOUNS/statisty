<?php

declare(strict_types=1);

namespace Statisty\Support;

final class ModelName
{
    public static function short(string $model): string
    {
        $parts = explode('\\', $model);

        return end($parts) ?: $model;
    }

    public static function label(string $model): string
    {
        return trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', self::short($model)));
    }
}
