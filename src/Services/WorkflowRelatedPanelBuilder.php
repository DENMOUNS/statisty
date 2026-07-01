<?php

declare(strict_types=1);

namespace Statisty\Services;

use Statisty\Discovery\RelationshipProfile;
use Statisty\Support\DisplayRowFetcher;
use Statisty\Support\ModelName;
use Statisty\Support\ModelSchema;

final class WorkflowRelatedPanelBuilder
{
    public function buildRelatedPanels(string $modelClass): array
    {
        $configRelations = (array) config('statisty.models.' . $modelClass . '.relations', []);
        if ($configRelations === []) {
            return [];
        }

        try {
            $profiler = app(RelationshipProfile::class);
            $profiledRelations = $profiler->profileModel($modelClass);
        } catch (\Throwable) {
            $profiledRelations = [];
        }

        $panels = [];
        $webPrefix = trim((string) config('statisty.routes.web.prefix', 'web/statisty'), '/');

        foreach ($configRelations as $relationName => $relConfig) {
            if (! isset($profiledRelations[$relationName])) {
                continue;
            }

            $relatedClass = $profiledRelations[$relationName]['related'] ?? null;
            $relationType = $profiledRelations[$relationName]['type'] ?? 'Unknown';

            if (! $relatedClass || ! class_exists($relatedClass)) {
                continue;
            }

            $wantedCols = (array) ($relConfig['columns'] ?? []);
            $availableCols = ModelSchema::displayColumns($relatedClass);
            $cols = $wantedCols !== []
                ? array_values(array_intersect($wantedCols, $availableCols))
                : array_slice($availableCols, 0, 5);

            if ($cols === []) {
                continue;
            }

            try {
                $relatedCount = (int) $relatedClass::query()->count();
                $sample = DisplayRowFetcher::fetch($relatedClass, $cols, 15);
            } catch (\Throwable) {
                $relatedCount = 0;
                $sample = [];
            }

            $panels[] = [
                'relationName' => $relationName,
                'label' => ucwords(str_replace(['_', 'Items'], [' ', ' Items'], $relationName)),
                'type' => $relationType,
                'relatedClass' => $relatedClass,
                'relatedLabel' => ModelName::label($relatedClass),
                'columns' => $cols,
                'count' => $relatedCount,
                'sample' => $sample,
                'workflowUrl' => url($webPrefix . '/workflow/' . str_replace('\\', '%5C', $relatedClass)),
            ];
        }

        return $panels;
    }
}
