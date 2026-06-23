<?php

declare(strict_types=1);

namespace Statisty\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Statisty\Discovery\ModelProfiler;
use Statisty\Discovery\TableInspector;
use Statisty\Cache\ProfilingCache;

final class DiscoveryController extends Controller
{
    public function show(Request $request, string $table, TableInspector $inspector, ProfilingCache $cache = null)
    {
        try {
            $profiler = app(\Statisty\Discovery\ModelProfiler::class);
            // Discovery is by table name; no model authorization here but respect disabled_models config referencing table names
            $disabled = (array) config('statisty.disabled_models', []);
            if (in_array($table, $disabled, true)) {
                return response()->json(['error' => 'model_disabled'], 403);
            }

            if ($cache) {
                $data = $cache->remember($table, fn() => $profiler->profile($table), config('statisty.cache.ttl', 300));
            } else {
                $data = $profiler->profile($table);
            }

            return response()->json($data);
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                return response()->json(['error' => 'server_error', 'message' => $e->getMessage()], 500);
            }

            return response()->json(['error' => 'server_error'], 500);
        }
    }
}
