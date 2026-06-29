<?php

declare(strict_types=1);

namespace Statisty\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Statisty\Support\ModelSchema;

final class TableRowResource extends JsonResource
{
    private static array $hiddenCache = [];
    private static array $modelInstances = [];

    public function toArray($request): array
    {
        $modelClass = $request->route('model') ?? $request->get('statisty_model');

        $hidden = $this->resolveHiddenColumns($modelClass);

        $data = parent::toArray($request);

        // remove hidden columns from root model data
        foreach ($hidden as $col) {
            if (array_key_exists($col, $data)) {
                unset($data[$col]);
            }
        }

        // If caller requested specific columns, support flattened relation columns like 'user.email'
        $requested = $request->query('columns', []);
        if (is_array($requested) && count($requested) > 0) {
            foreach ($requested as $col) {
                if (! is_string($col) || ! str_contains($col, '.')) {
                    continue;
                }

                [$rel, $attr] = explode('.', $col, 2);

                try {
                    $root = $this->resolveModelInstance($modelClass);
                    if (! $root || ! ModelSchema::isVisibleRelationColumn($root, $rel, $attr)) {
                        continue;
                    }

                    $relVal = $this->resource->{$rel} ?? null;

                    if ($relVal === null) {
                        $data[$col] = null;
                        continue;
                    }

                    // If collection, join values
                    if ($relVal instanceof \Illuminate\Support\Collection || $relVal instanceof \Illuminate\Database\Eloquent\Collection) {
                        $vals = $relVal->pluck($attr)->filter()->values()->all();
                        $data[$col] = implode(', ', $vals);
                        continue;
                    }

                    // If related model
                    if (is_object($relVal) && isset($relVal->{$attr})) {
                        $data[$col] = $relVal->{$attr};
                        continue;
                    }

                    // fallback to null
                    $data[$col] = null;
                } catch (\Throwable) {
                    $data[$col] = null;
                }
            }
        }

        return $data;
    }

    private function resolveHiddenColumns(?string $modelClass): array
    {
        $hidden = (array) config('statisty.hidden_columns', []);

        if (! $modelClass || ! class_exists($modelClass)) {
            return $hidden;
        }

        if (! isset(self::$hiddenCache[$modelClass])) {
            try {
                $instance = $this->resolveModelInstance($modelClass);
                if ($instance && method_exists($instance, 'getHidden')) {
                    self::$hiddenCache[$modelClass] = array_merge($hidden, (array) $instance->getHidden());
                } else {
                    self::$hiddenCache[$modelClass] = $hidden;
                }
            } catch (\Throwable) {
                self::$hiddenCache[$modelClass] = $hidden;
            }
        }

        return self::$hiddenCache[$modelClass];
    }

    private function resolveModelInstance(string $modelClass): ?object
    {
        if (! isset(self::$modelInstances[$modelClass])) {
            try {
                self::$modelInstances[$modelClass] = new $modelClass();
            } catch (\Throwable) {
                self::$modelInstances[$modelClass] = null;
            }
        }

        return self::$modelInstances[$modelClass];
    }
}
