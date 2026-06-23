<?php

declare(strict_types=1);

namespace Statisty\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Statisty\Support\ApiError;
use Statisty\Support\StatistyAuthorization;

final class EnsureStatistyAuthorized
{
    public function handle(Request $request, Closure $next)
    {
        $model = $request->route('model') ?? $request->route('table') ?? $request->get('statisty_model');

        if (! StatistyAuthorization::allows($request, is_string($model) ? $model : null)) {
            return ApiError::response('unauthorized', 403);
        }

        return $next($request);
    }
}
