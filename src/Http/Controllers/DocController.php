<?php

declare(strict_types=1);

namespace Statisty\Http\Controllers;

use Illuminate\Http\Request;
use Statisty\Http\Services\OpenApiSpecBuilder;
use Statisty\Http\Services\RouteDocumentationCollector;

final class DocController extends BaseDashboardController
{
    public function index(Request $request, RouteDocumentationCollector $collector)
    {
        ['apiDocs' => $apiDocs, 'webRoutes' => $webRoutes, 'apiPrefix' => $apiPrefix, 'webPrefix' => $webPrefix] = $collector->collectRouteDocs();

        return view('statisty::doc', array_merge($this->shellData('docs'), [
            'apiDocs' => $apiDocs,
            'webRoutes' => $webRoutes,
            'apiPrefix' => $apiPrefix,
            'webPrefix' => $webPrefix,
        ]));
    }

    public function openApi(Request $request, RouteDocumentationCollector $collector, OpenApiSpecBuilder $specBuilder)
    {
        $routeDocs = $collector->collectRouteDocs();

        return response()->json(
            $specBuilder->build($routeDocs['apiDocs']),
            200,
            [],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );
    }
}
