<?php

declare(strict_types=1);

namespace Statisty\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

final class DocController extends BaseDashboardController
{
    public function index(Request $request)
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

            // Exclude statisty package routes and common dev tool routes
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
                'methods' => array_filter($methods, fn($m) => $m !== 'HEAD'),
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

            // If action points to a controller method (e.g. App\Http\Controllers\UserController@index)
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

                        // Inspect method parameters to check for FormRequests, DTOs, or other injectables
                        foreach ($refMethod->getParameters() as $param) {
                            $type = $param->getType();
                            if ($type !== null && !$type->isBuiltin()) {
                                $className = $type->getName();
                                
                                // 1. Form Requests
                                if (is_subclass_of($className, \Illuminate\Foundation\Http\FormRequest::class)) {
                                    $rules = $this->extractFormRequestRules($className);
                                    if ($rules !== []) {
                                        $routeInfo['validation_rules'] = array_merge($routeInfo['validation_rules'], $rules);
                                    }
                                } 
                                // 2. Ignore Laravel core classes/services
                                elseif (str_starts_with($className, 'Illuminate\\') || str_starts_with($className, 'Symfony\\')) {
                                    continue;
                                }
                                // 3. Try to extract public properties from potential DTOs
                                elseif (class_exists($className)) {
                                    $dtoProps = $this->extractDtoProperties($className);
                                    if ($dtoProps !== []) {
                                        $routeInfo['validation_rules'] = array_merge($routeInfo['validation_rules'], $dtoProps);
                                    }
                                }
                            }
                        }

                        // Add manually documented parameters from DocBlock
                        if (!empty($parsedDoc['manual_params'])) {
                            $routeInfo['validation_rules'] = array_merge($routeInfo['validation_rules'], $parsedDoc['manual_params']);
                        }

                        // Extract URI parameter patterns
                        preg_match_all('/\{([a-zA-Z0-9_?]+)\}/', $uri, $matches);
                        if (! empty($matches[1])) {
                            foreach ($matches[1] as $paramName) {
                                $isOptional = str_ends_with($paramName, '?');
                                $cleanName = rtrim($paramName, '?');
                                $routeInfo['params'][] = [
                                    'name' => $cleanName,
                                    'required' => !$isOptional,
                                    'type' => 'string/integer',
                                ];
                            }
                        }
                    }
                } catch (Throwable $e) {
                    // Fail-safe: fallback to default empty descriptions on Reflection error
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

        usort($webRoutes, fn($a, $b) => strcmp($a['uri'], $b['uri']));
        usort($apiDocs, fn($a, $b) => strcmp($a['uri'], $b['uri']));

        return view('statisty::doc', array_merge($this->shellData('docs'), [
            'apiDocs' => $apiDocs,
            'webRoutes' => $webRoutes,
            'apiPrefix' => $apiPrefix,
            'webPrefix' => $webPrefix,
        ]));
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

            // Extract @bodyParam or @queryParam (e.g. @bodyParam name string required The user name)
            if (str_starts_with($line, '@bodyParam') || str_starts_with($line, '@queryParam')) {
                $paramType = str_starts_with($line, '@bodyParam') ? 'Body' : 'Query';
                // Remove the tag itself
                $content = trim(preg_replace('/^@(body|query)Param\s+/', '', $line));
                // Split by spaces: [name] [type] [required/optional] [description...]
                $parts = preg_split('/\s+/', $content, 4);
                
                if (count($parts) >= 1) {
                    $fieldName = $parts[0];
                    $fieldType = $parts[1] ?? 'string';
                    $reqStr = strtolower($parts[2] ?? '');
                    $isRequired = ($reqStr === 'required');
                    $desc = $parts[3] ?? '';
                    
                    if ($reqStr !== 'required' && $reqStr !== 'optional') {
                        // If the 3rd arg isn't required/optional, it's probably part of the description
                        $desc = trim(($parts[2] ?? '') . ' ' . $desc);
                    }

                    $manualParams[] = [
                        'field' => $fieldName,
                        'rules' => $fieldType . " ($paramType) " . $desc,
                        'required' => $isRequired,
                    ];
                }
                continue;
            }

            if (str_starts_with($line, '@')) {
                // Ignore other annotation tags like @param, @author, etc.
                continue;
            }

            $descriptionLines[] = $line;
        }

        return [
            'description' => count($descriptionLines) > 0 ? implode(' ', $descriptionLines) : 'Aucune description disponible.',
            'is_deprecated' => $isDeprecated,
            'return_type' => $returnType,
            'manual_params' => $manualParams,
        ];
    }

    private function extractFormRequestRules(string $formRequestClass): array
    {
        $rulesList = [];

        try {
            $currentRequest = app(Request::class);
        } catch (Throwable $e) {
            $currentRequest = Request::createFromGlobals();
        }

        try {
            $request = app()->make($formRequestClass);

            if ($request instanceof \Illuminate\Foundation\Http\FormRequest) {
                $request->setContainer(app());

                if (app()->bound('redirect')) {
                    $request->setRedirector(app('redirect'));
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
                        $ruleStr = implode('|', array_map(fn($r) => is_string($r) ? $r : get_class($r), $fieldRules));
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
        } catch (Throwable $e) {
            // Ignore instantiation issues
        }

        return $rulesList;
    }

    private function extractDtoProperties(string $className): array
    {
        $propertiesList = [];
        try {
            $refClass = new ReflectionClass($className);
            // Only extract public properties as they are typically what is exposed/expected for binding
            $properties = $refClass->getProperties(\ReflectionProperty::IS_PUBLIC);
            
            foreach ($properties as $property) {
                $type = $property->getType();
                $typeStr = $type ? (method_exists($type, 'getName') ? $type->getName() : (string) $type) : 'mixed';
                
                // If it's strongly typed and doesn't allow null, it's required
                $isRequired = $type && !$type->allowsNull();
                
                $propertiesList[] = [
                    'field' => $property->getName(),
                    'rules' => "Type: {$typeStr} (DTO Property)",
                    'required' => $isRequired,
                ];
            }
        } catch (Throwable $e) {
            // Ignore reflection issues
        }

        return $propertiesList;
    }
}
