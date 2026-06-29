<?php

declare(strict_types=1);

namespace Statisty\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class StatistyAuthorization
{
    private static $callback = null;

    public static function authorizeUsing(?callable $callback): void
    {
        self::$callback = $callback;
    }

    public static function allows(Request $request, ?string $model = null): bool
    {
        if (! config('statisty.security.enforce_authorization', true)) {
            return true;
        }

        if (self::$callback !== null) {
            return (bool) call_user_func(self::$callback, $request, $model);
        }

        $configured = config('statisty.authorization_callback');
        if (is_callable($configured)) {
            return (bool) $configured($request, $model);
        }

        if ($model === null || ! class_exists($model)) {
            return false;
        }

        try {
            return ! Gate::denies('viewAny', $model);
        } catch (\Throwable) {
            return false;
        }
    }
}
