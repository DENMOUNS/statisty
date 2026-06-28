<?php

declare(strict_types=1);

namespace Statisty\Graph;

use Statisty\Charts\ChartDataGenerator;
use Statisty\Contracts\ChartGeneratorContract;
use Statisty\Support\ModelName;
use Statisty\Workspace\WorkspaceDefinition;

/**
 * Générateur de charts automatiques basé sur la définition du workspace.
 *
 * FIX 4a — `generateFromModel()` délègue désormais à `ChartDataGenerator`
 * au lieu de dupliquer la logique driver-spécifique. Une seule classe
 * contient la vraie implémentation SQL ; `AutomaticChartGenerator` se
 * charge uniquement de construire les définitions workspace + de router.
 */
final class AutomaticChartGenerator implements ChartGeneratorContract
{
    public function __construct(
        private readonly ChartDataGenerator $generator = new ChartDataGenerator(),
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Contrat ChartGeneratorContract — construit les définitions par workspace
    // ─────────────────────────────────────────────────────────────────────────

    public function generate(WorkspaceDefinition $workspace): array
    {
        $automatic = array_map(
            fn (string $model): ChartDefinition => new ChartDefinition(
                name: ModelName::label($model) . ' Trend',
                type: ChartType::LINE,
                model: $model,
                field: null,
                options: [
                    'x_axis'      => $workspace->options->dateColumn,
                    'aggregation' => 'count',
                    'date_from'   => $workspace->options->dateFrom,
                    'date_to'     => $workspace->options->dateTo,
                    'filters'     => $workspace->options->filters,
                    'timezone'    => $workspace->options->timezone,
                ],
            ),
            $workspace->models,
        );

        return array_merge($automatic, $workspace->customCharts);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FIX 4a — Délégation à ChartDataGenerator (supprime le code dupliqué)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Génère les données d'un chart pour un modèle donné.
     * Toute la logique SQL (MySQL / SQLite / Postgres) est dans ChartDataGenerator.
     */
    public function generateFromModel(
        string $model,
        ?string $value,
        string $dateColumn,
        array $options = [],
    ): array {
        return $this->generator->generateFromModel($model, $value, $dateColumn, $options);
    }
}