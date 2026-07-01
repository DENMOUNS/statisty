<?php

declare(strict_types=1);

namespace Statisty\Http\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Http\FormRequest;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use Throwable;

final class RouteDocumentationCollector
{
    private CacheRepository $cache;

    public function __construct(CacheRepository $cache)
    {
        $this->cache = $cache;
    }

    public function collectRouteDocs(): array
    {
        if (! config('statisty.cache.enabled', true)) {
            return $this->gatherRouteDocsFromRoutes();
        }

        return $this->cache->remember(
            $this->cacheKey(),
            $this->cacheTtl(),
            fn () => $this->gatherRouteDocsFromRoutes(),
        );
    }

    private function gatherRouteDocsFromRoutes(): array
    {
        $allRoutes = Route::getRoutes()->getRoutes();
        $apiDocs = [];
        $webRoutes = [];

        $apiPrefix = trim((string) config('statisty.routes.api.prefix', 'api/statisty'), '/');
        $webPrefix = trim((string) config('statisty.routes.web.prefix', 'web/statisty'), '/');

        foreach ($allRoutes as $route) {
            $uri = $route->uri();
            $methods = $route->methods();
            $name = $route->getName() ?? '';
            $actionName = $route->getActionName();
            $middleware = $route->gatherMiddleware();

            if (
                str_starts_with($uri, trim($apiPrefix, '/')) ||
                str_starts_with($uri, trim($webPrefix, '/')) ||
                str_starts_with($name, 'statisty.') ||
                str_starts_with($uri, '_debugbar') ||
                str_starts_with($uri, '_ignition') ||
                str_starts_with($uri, 'sanctum/csrf-cookie') ||
                str_contains($actionName, 'Statisty\\')
            ) {
                continue;
            }

            $routeTypeInfo = $this->determineRouteType($route, $apiPrefix, $webPrefix);
            $routeInfo = [
                'uri' => '/' . ltrim($uri, '/'),
                'methods' => array_filter($methods, fn ($m) => $m !== 'HEAD'),
                'name' => $name,
                'action' => $actionName,
                'middleware' => $middleware,
                'description' => 'Aucune description disponible.',
                'params' => [],
                'validation_rules' => [],
                'response_type' => null,
                'is_deprecated' => false,
                'type' => $routeTypeInfo['type'],
                'source_hint' => $routeTypeInfo['source_hint'],
            ];

            if (is_string($actionName) && str_contains($actionName, '@')) {
                [$controller, $method] = explode('@', $actionName);

                try {
                    if (class_exists($controller) && method_exists($controller, $method)) {
                        $refMethod = new ReflectionMethod($controller, $method);
                        $docComment = $refMethod->getDocComment();

                        if ($docComment !== false) {
                            $parsedDoc = $this->parseDocComment($docComment);
                            $routeInfo['description'] = $parsedDoc['description'];
                            $routeInfo['is_deprecated'] = $parsedDoc['is_deprecated'];
                            $routeInfo['response_type'] = $parsedDoc['return_type'];
                        }

                        foreach ($refMethod->getParameters() as $param) {
                            $type = $param->getType();
                            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                                $className = $type->getName();

                                if (is_subclass_of($className, FormRequest::class)) {
                                    $rules = $this->extractFormRequestRules($className);
                                    if ($rules !== []) {
                                        $routeInfo['validation_rules'] = array_merge($routeInfo['validation_rules'], $rules);
                                    }
                                } elseif (str_starts_with($className, 'Illuminate\\') || str_starts_with($className, 'Symfony\\')) {
                                    continue;
                                } elseif (class_exists($className)) {
                                    $dtoProps = $this->extractDtoProperties($className);
                                    if ($dtoProps !== []) {
                                        $routeInfo['validation_rules'] = array_merge($routeInfo['validation_rules'], $dtoProps);
                                    }
                                }
                            }
                        }

                        if (! empty($parsedDoc['manual_params'])) {
                            $routeInfo['validation_rules'] = array_merge($routeInfo['validation_rules'], $parsedDoc['manual_params']);
                        }

                        preg_match_all('/\{([a-zA-Z0-9_?]+)\}/', $uri, $matches);
                        if (! empty($matches[1])) {
                            foreach ($matches[1] as $paramName) {
                                $isOptional = str_ends_with($paramName, '?');
                                $cleanName = rtrim($paramName, '?');
                                $routeInfo['params'][] = [
                                    'name' => $cleanName,
                                    'required' => ! $isOptional,
                                    'type' => 'string/integer',
                                ];
                            }
                        }
                    }
                } catch (Throwable) {
                    // Fail-safe : fallback to defaults.
                }
            } else {
                $routeInfo['action'] = 'Closure / Callback';
            }

            if ($routeInfo['type'] === 'api') {
                $apiDocs[] = $routeInfo;
            } else {
                $webRoutes[] = $routeInfo;
            }
        }

        usort($webRoutes, fn ($a, $b) => strcmp($a['uri'], $b['uri']));
        usort($apiDocs, fn ($a, $b) => strcmp($a['uri'], $b['uri']));

        return [
            'apiDocs' => $apiDocs,
            'webRoutes' => $webRoutes,
            'apiPrefix' => $apiPrefix,
            'webPrefix' => $webPrefix,
        ];
    }

    private function cacheKey(): string
    {
        $prefix = config('statisty.cache.prefix', 'statisty');
        $version = config('statisty.cache.version', 'v1');

        return sprintf('%s:route_docs:%s', $prefix, $version);
    }

    private function cacheTtl(): int
    {
        return max(1, (int) config('statisty.cache.ttl', 300));
    }

    private function determineRouteType($route, string $apiPrefix, string $webPrefix): array
    {
        $uri = trim($route->uri(), '/');
        $middleware = $route->gatherMiddleware();

        if ($apiPrefix !== '' && str_starts_with($uri, trim($apiPrefix, '/'))) {
            return ['type' => 'api', 'source_hint' => 'Préfixe API'];
        }

        if (in_array('api', $middleware, true) || in_array('api:api', $middleware, true) || in_array('throttle:api', $middleware, true)) {
            return ['type' => 'api', 'source_hint' => 'Middleware API'];
        }

        if ($webPrefix !== '' && str_starts_with($uri, trim($webPrefix, '/'))) {
            return ['type' => 'web', 'source_hint' => 'Préfixe Web'];
        }

        if (in_array('web', $middleware, true) || in_array('web:api', $middleware, true)) {
            return ['type' => 'web', 'source_hint' => 'Middleware Web'];
        }

        return ['type' => 'web', 'source_hint' => 'Détection par défaut'];
    }

    private function parseDocComment(string $docComment): array
    {
        $lines = explode("\n", $docComment);
        $descriptionLines = [];
        $isDeprecated = false;
        $returnType = null;
        $manualParams = [];

        foreach ($lines as $line) {
            $line = trim($line, "/* \t\r\n");
            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '@deprecated')) {
                $isDeprecated = true;
                continue;
            }

            if (str_starts_with($line, '@return')) {
                $returnType = trim(str_replace('@return', '', $line));
                continue;
            }

            if (str_starts_with($line, '@bodyParam') || str_starts_with($line, '@queryParam')) {
                $paramType = str_starts_with($line, '@bodyParam') ? 'Body' : 'Query';
                $content = trim(preg_replace('/^@(body|query)Param\s+/', '', $line));
                $parts = preg_split('/\s+/', $content, 4);

                if (count($parts) >= 1) {
                    $fieldName = $parts[0];
                    $fieldType = $parts[1] ?? 'string';
                    $reqStr = strtolower($parts[2] ?? '');
                    $isRequired = ($reqStr === 'required');
                    $desc = $parts[3] ?? '';

                    if ($reqStr !== 'required' && $reqStr !== 'optional') {
                        $desc = trim(($parts[2] ?? '') . ' ' . $desc);
                    }

                    $manualParams[] = [
                        'field' => $fieldName,
                        'rules' => $fieldType . " ({$paramType}) " . $desc,
                        'required' => $isRequired,
                    ];
                }

                continue;
            }

            if (str_starts_with($line, '@')) {
                continue;
            }

            $descriptionLines[] = $line;
        }

        return [
            'description' => $descriptionLines !== [] ? implode(' ', $descriptionLines) : 'Aucune description disponible.',
            'is_deprecated' => $isDeprecated,
            'return_type' => $returnType,
            'manual_params' => $manualParams,
        ];
    }

    private function extractFormRequestRules(string $formRequestClass): array
    {
        $rulesList = [];

        try {
            $currentRequest = app('Illuminate\Http\Request');
        } catch (Throwable) {
            $currentRequest = \Illuminate\Http\Request::createFromGlobals();
        }

        try {
            $request = app()->make($formRequestClass);

            if ($request instanceof FormRequest) {
                $request->setContainer(app());

                if (app()->bound('redirect')) {
                    /** @var \Illuminate\Routing\Redirector $redirector */
                    $redirector = app('redirect');
                    $request->setRedirector($redirector);
                }

                $request->setRouteResolver(fn () => $currentRequest->route());
                $request->setUserResolver(fn () => $currentRequest->user());
                $request->initialize(
                    $currentRequest->query->all(),
                    $currentRequest->request->all(),
                    $currentRequest->attributes->all(),
                    $currentRequest->cookies->all(),
                    $currentRequest->files->all(),
                    $currentRequest->server->all(),
                    $currentRequest->getContent(),
                );
            }

            if (method_exists($request, 'rules')) {
                $rules = $request->rules();
                foreach ($rules as $field => $fieldRules) {
                    if (is_array($fieldRules)) {
                        $ruleStr = implode('|', array_map(fn ($r) => is_string($r) ? $r : get_class($r), $fieldRules));
                    } else {
                        $ruleStr = (string) $fieldRules;
                    }

                    $isRequired = str_contains($ruleStr, 'required');
                    $rulesList[] = [
                        'field' => $field,
                        'rules' => $ruleStr,
                        'required' => $isRequired,
                    ];
                }
            }
        } catch (Throwable) {
            // ignore instantiation issues
        }

        return $rulesList;
    }

    private function extractDtoProperties(string $className): array
    {
        if ($this->isFrameworkRequestClass($className)) {
            return [];
        }

        $propertiesList = [];

        try {
            $refClass = new ReflectionClass($className);
            $properties = $refClass->getProperties(ReflectionProperty::IS_PUBLIC);

            foreach ($properties as $property) {
                $type = $property->getType();
                $typeStr = $type ? (method_exists($type, 'getName') ? $type->getName() : (string) $type) : 'mixed';
                $isRequired = $type && ! $type->allowsNull();

                $propertiesList[] = [
                    'field' => $property->getName(),
                    'rules' => "Type: {$typeStr} (DTO Property)",
                    'required' => $isRequired,
                ];
            }
        } catch (Throwable) {
            // ignore reflection issues
        }

        return $propertiesList;
    }

    private function isFrameworkRequestClass(string $className): bool
    {
        return str_starts_with($className, 'Illuminate\\')
            || str_starts_with($className, 'Symfony\\')
            || str_starts_with($className, 'Psr\\');
    }
}
