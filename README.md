# Statisty

Statisty fournit des outils pour construire rapidement des tableaux de bord analytiques.

Usage rapide

1. Publiez la configuration:

```bash
php artisan vendor:publish --tag=statisty-config
```

2. Configurez les modèles exposés dans `config/statisty.php` (allow-list).

3. Exemple de définition de KPI via builder:

```php
use Statisty\Support\Builders\KpiBuilder;

KpiBuilder::make('total_sales')
    ->model(App\Models\Order::class)
    ->field('total')
    ->toArray();
```

4. Vérifiez l'installation:

```bash
php artisan statisty:doctor
```

Pour la liste complète des endpoints et options, voir `docs/API.md`.
# Statisty

Laravel Analytics & Observability Engine.

Statisty turns Eloquent models into workspace-based dashboard definitions. The current package foundation focuses on the MVP API surface and serializable dashboard structure; query execution, advanced discovery, and UI rendering are intentionally built in later layers.

## Installation

```bash
composer require statisty/statisty
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=statisty-config
```

## Quick Start

```php
use Statisty\Facades\Statisty;

$dashboard = Statisty::workspace('business')
    ->models([
        App\Models\User::class,
        App\Models\Order::class,
    ])
    ->pagination(500)
    ->build();

return $dashboard->toArray();
```

The static core entry point is also available:

```php
use Statisty\Core\Statisty;

$dashboard = Statisty::workspace('business')
    ->models([App\Models\User::class])
    ->build();
```

## Current MVP Foundation

- Workspace builder API
- Automatic dashboard definition generation
- KPI, chart, table, and relationship definition placeholders
- Serializable `toArray()` dashboard output
- Laravel service provider
- Laravel facade
- Publishable configuration

## Roadmap

### MVP

- Validate Eloquent model classes
- Profile model columns and relationships
- Execute real `count`, `sum`, and conversion metrics
- Generate chart-ready datasets
- Build paginated Eloquent tables
- Cache dashboard rendering

### V2

- Funnel analytics
- Cohort analysis
- Advanced filters and drill-down
- Cross-model metrics
- Business event tracking foundation

### V3

- Observability correlation
- Anomaly detection
- Root cause analysis
- AI-generated insights
- Predictive analytics
