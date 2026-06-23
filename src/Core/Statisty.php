<?php

declare(strict_types=1);

namespace Statisty\Core;

use Statisty\Support\StatistyAuthorization;
use Statisty\Workspace\WorkspaceBuilder;

final class Statisty
{
    public static function workspace(?string $name = null): WorkspaceBuilder
    {
        return (new StatistyManager())->workspace($name);
    }

    public static function authorizeUsing(callable $callback): void
    {
        StatistyAuthorization::authorizeUsing($callback);
    }
}
