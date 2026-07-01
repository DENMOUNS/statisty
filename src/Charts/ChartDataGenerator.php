<?php

declare(strict_types=1);

namespace Statisty\Charts;

final class ChartDataGenerator
{
    public function generateFromModel(string $modelClass, ?string $valueField = null, string $dateColumn = 'created_at', array $options = []): array
    {
        $validator = new ChartDataRequestValidator();
        $contextBuilder = new ChartQueryContextBuilder();
        $processor = new ChartDataProcessor();
        $responseBuilder = new ChartDataResponseBuilder();

        $validator->validate($modelClass, $valueField, $dateColumn);

        $period = $this->normalizePeriod($options['period'] ?? 'day');
        $label = $options['label'] ?? $this->shortClass($modelClass);
        $maxPie = (int) ($options['max_categories_for_pie'] ?? 12);

        $context = $contextBuilder->buildContext($modelClass, $valueField, $dateColumn);
        $contextBuilder->applyDateFilters(
            $context['query'],
            $options['from'] ?? null,
            $options['to'] ?? null,
            $context['dateColumnPrefixed'],
        );

        $rows = $processor->resolveRows(
            $context['query'],
            $valueField,
            $dateColumn,
            $context['dateColumnPrefixed'],
            $context['isRelation'],
            $context['relationField'],
            $context['valueFieldToAggregate'],
            $options,
            $period,
        );

        return $responseBuilder->build($rows, $valueField, $label, $options, $period, $processor, $maxPie);
    }

    private function normalizePeriod(?string $period): string
    {
        $allowed = ['day', 'week', 'month', 'year'];

        return in_array($period, $allowed, true) ? $period : 'day';
    }

    private function shortClass(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }
}
