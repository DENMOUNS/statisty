<?php

declare(strict_types=1);

namespace Statisty\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class WorkspaceController extends Controller
{
    public function show(Request $request, string $name)
    {
        try {
            // Minimal: return workspace config
            $workspaces = config('statisty.workspaces', config('statisty.workspace', []));

            if (! isset($workspaces[$name])) {
                return response()->json(['error' => 'workspace_not_found'], 404);
            }

            return response()->json($workspaces[$name]);
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                return response()->json(['error' => 'server_error', 'message' => $e->getMessage()], 500);
            }

            return response()->json(['error' => 'server_error'], 500);
        }
    }
}
