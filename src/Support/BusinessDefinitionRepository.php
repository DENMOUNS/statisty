<?php

declare(strict_types=1);

namespace Statisty\Support;

final class BusinessDefinitionRepository
{
    public static function get(string $type, ?string $name): ?array
    {
        if ($name === null) {
            return null;
        }

        $definition = config("statisty.definitions.{$type}.{$name}");

        return is_array($definition) ? $definition : null;
    }

    public static function all(string $type): array
    {
        return (array) config("statisty.definitions.{$type}", []);
    }

    public static function add(string $type, string $name, array $definition): void
    {
        $definitions = self::all($type);
        $definitions[$name] = $definition;
        config(["statisty.definitions.{$type}" => $definitions]);
    }
}
