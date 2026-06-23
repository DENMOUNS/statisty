<?php

declare(strict_types=1);

namespace Statisty\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class DashboardController extends Controller
{
    public function index(Request $request)
    {
        try {
            // minimal dashboard payload
            return response()->json([
                'name' => config('app.name'),
                'statisty_version' => config('statisty.version', '1.0.0'),
            ]);
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                return response()->json(['error' => 'server_error', 'message' => $e->getMessage()], 500);
            }

            return response()->json(['error' => 'server_error'], 500);
        }
    }
}
