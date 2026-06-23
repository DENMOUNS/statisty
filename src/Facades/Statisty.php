<?php

declare(strict_types=1);

namespace Statisty\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Statisty\Workspace\WorkspaceBuilder workspace(string $name)
 */
final class Statisty extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'statisty';
    }
}
