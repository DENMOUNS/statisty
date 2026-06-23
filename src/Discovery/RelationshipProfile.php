<?php

declare(strict_types=1);

namespace Statisty\Discovery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

final class RelationshipProfile
{
    /**
     * Inspecte un modèle Eloquent et tente d'identifier ses relations.
     * Retourne un tableau: [relationName => ['type' => 'HasMany', 'related' => RelatedClass]]
     */
    public function profileModel(string $modelClass): array
    {
        if (! class_exists($modelClass)) {
            throw new \InvalidArgumentException("Model class [{$modelClass}] not found.");
        }

        $model = new $modelClass();

        if (! $model instanceof Model) {
            return [];
        }

        $results = [];

        $ref = new \ReflectionClass($model);
        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $ref->getName()) {
                continue;
            }

            if ($method->getNumberOfParameters() > 0) {
                continue;
            }

            $name = $method->getName();

            try {
                $relation = $model->{$name}();
            } catch (\Throwable $e) {
                continue;
            }

            if (! $relation instanceof Relation) {
                continue;
            }

            $related = get_class($relation->getRelated());
            $type = (new \ReflectionClass($relation))->getShortName();

            $results[$name] = [
                'type' => $type,
                'related' => $related,
            ];
        }

        return $results;
    }
}
