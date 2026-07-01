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
     *
     * On détecte aussi les relations héritées d'une classe parente
     * ou définies dans un trait, et pas uniquement celles déclarées
     * directement sur la classe du modèle.
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
            $declaringClass = $method->getDeclaringClass()->getName();

            if (str_starts_with($declaringClass, 'Illuminate\\')) {
                continue;
            }

            if ($method->getNumberOfRequiredParameters() > 0 || $method->isStatic() || $method->isAbstract()) {
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