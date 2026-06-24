<?php

declare(strict_types=1);

namespace Statisty\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Statisty\Core\StatistyManager;
use Statisty\Support\ModelName;
use Statisty\Support\ModelSchema;

final class DashboardController extends Controller
{
    public function index(Request $request, StatistyManager $statisty)
    {
        try {
            $models = $this->dashboardModels();

            if ($models === []) {
                return view('statisty::dashboard', [
                    'appName' => config('app.name'),
                    'version' => config('statisty.version', '1.0.0'),
                    'workspace' => null,
                    'kpis' => [],
                    'models' => [],
                    ...$this->shellData('dashboard'),
                    'emptyMessage' => 'No Statisty models are configured yet.',
                ]);
            }

            $dashboard = $statisty
                ->workspace((string) config('statisty.workspace.default', 'default'))
                ->models($models)
                ->pagination((int) config('statisty.pagination.default', 25))
                ->withoutCache()
                ->build();

            return view('statisty::dashboard', [
                'appName' => config('app.name'),
                'version' => config('statisty.version', '1.0.0'),
                'workspace' => $dashboard->workspace,
                'kpis' => $dashboard->kpis,
                'models' => $this->modelCards($models),
                ...$this->shellData('dashboard'),
                'emptyMessage' => null,
            ]);
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                return response()->json(['error' => 'server_error', 'message' => $e->getMessage()], 500);
            }

            return response()->json(['error' => 'server_error'], 500);
        }
    }

    public function workflow(Request $request, string $model)
    {
        // Unescape backslashes in model class namespace
        $modelClass = str_replace('%5C', '\\\\', $model);
        if (!str_starts_with($modelClass, '\\\\')) {
            $modelClass = '\\\\' . $modelClass;
        }
        $modelClass = ltrim($modelClass, '\\\\');

        if (ModelSchema::isDisabledModel($modelClass)) {
            abort(403, 'Model is disabled.');
        }

        if (!ModelSchema::isQueryableModel($modelClass)) {
            abort(404, 'Model is not queryable.');
        }

        try {
            $totalCount = (int) $modelClass::query()->count();
            $columns = ModelSchema::visibleColumns($modelClass);

            // Search for numeric fields to build custom indicators (sum, average)
            $numericColumns = [];
            foreach ($columns as $column) {
                if (in_array(strtolower($column), ['amount', 'total', 'price', 'quantity', 'value', 'points', 'sum', 'count', 'score', 'total_amount'])) {
                    $numericColumns[] = $column;
                }
            }

            $kpis = [
                [
                    'label' => 'Total Records',
                    'value' => number_format($totalCount),
                    'sub'   => 'Global row count'
                ]
            ];

            foreach ($numericColumns as $col) {
                try {
                    $sum = $modelClass::query()->sum($col);
                    $avg = $modelClass::query()->avg($col);

                    $kpis[] = [
                        'label' => 'Total ' . ucfirst(str_replace('_', ' ', $col)),
                        'value' => is_numeric($sum) ? number_format((float) $sum, 2) : '0.00',
                        'sub'   => 'Sum of field values'
                    ];
                    $kpis[] = [
                        'label' => 'Avg ' . ucfirst(str_replace('_', ' ', $col)),
                        'value' => is_numeric($avg) ? number_format((float) $avg, 2) : '0.00',
                        'sub'   => 'Average field value'
                    ];
                } catch (\Throwable) {
                    // Ignore transient SQL errors on calculation
                }
            }

            // Fetch last 50 rows for the Datatable
            $recentLimit = 50;
            $rows = [];
            if (method_exists($modelClass, 'query')) {
                $query = $modelClass::query()->select($columns)->limit($recentLimit);
                if (in_array('created_at', $columns, true)) {
                    $query->latest('created_at');
                }
                $rows = $query->get()
                    ->map(fn (mixed $row): array => collect($row->toArray())
                        ->only($columns)
                        ->map(fn (mixed $val): mixed => is_scalar($val) || $val === null ? $val : json_encode($val))
                        ->all())
                    ->all();
            }

            // ── Build panels for relations declared in config
            $relatedPanels = $this->buildRelatedPanels($modelClass);

            return view('statisty::workflow', [
                'appName'       => config('app.name'),
                'version'       => config('statisty.version', '1.0.0'),
                'modelLabel'    => ModelName::label($modelClass),
                'modelClass'    => $modelClass,
                'kpis'          => $kpis,
                'columns'       => $columns,
                'rows'          => $rows,
                'chartUrl'      => $this->apiUrl('charts', $modelClass),
                'relatedPanels' => $relatedPanels,
                ...$this->shellData('dashboard'),
            ]);
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                abort(500, $e->getMessage());
            }
            abort(500, 'Server error');
        }
    }

    /**
     * Resolve related model panels from config + RelationshipProfile introspection.
     * Returns an array of panels, each with: relationName, label, type, relatedClass,
     * relatedLabel, columns, count, sample rows, workflowUrl.
     */
    private function buildRelatedPanels(string $modelClass): array
    {
        $configRelations = (array) config('statisty.models.' . $modelClass . '.relations', []);

        if ($configRelations === []) {
            return [];
        }

        $profiler = app(\Statisty\Discovery\RelationshipProfile::class);
        try {
            $profiledRelations = $profiler->profileModel($modelClass);
        } catch (\Throwable) {
            $profiledRelations = [];
        }

        $panels    = [];
        $webPrefix = trim((string) config('statisty.routes.web.prefix', 'web/statisty'), '/');

        foreach ($configRelations as $relationName => $relConfig) {
            if (!isset($profiledRelations[$relationName])) {
                continue;
            }

            $relatedClass = $profiledRelations[$relationName]['related'] ?? null;
            $relationType = $profiledRelations[$relationName]['type'] ?? 'Unknown';

            if (!$relatedClass || !class_exists($relatedClass)) {
                continue;
            }

            $wantedCols   = (array) ($relConfig['columns'] ?? []);
            $availableCols = ModelSchema::visibleColumns($relatedClass);
            $cols = $wantedCols !== []
                ? array_values(array_intersect($wantedCols, $availableCols))
                : array_slice($availableCols, 0, 5);

            if ($cols === []) {
                continue;
            }

            try {
                $relatedCount = (int) $relatedClass::query()->count();

                $sampleQuery = $relatedClass::query()->select($cols)->limit(15);
                if (in_array('created_at', $cols, true)) {
                    $sampleQuery->latest('created_at');
                }
                $sample = $sampleQuery->get()
                    ->map(fn (mixed $row): array => collect($row->toArray())
                        ->only($cols)
                        ->map(fn (mixed $v): mixed => is_scalar($v) || $v === null ? $v : json_encode($v))
                        ->all())
                    ->all();
            } catch (\Throwable) {
                $relatedCount = 0;
                $sample       = [];
            }

            $panels[] = [
                'relationName' => $relationName,
                'label'        => ucwords(str_replace(['_', 'Items'], [' ', ' Items'], $relationName)),
                'type'         => $relationType,
                'relatedClass' => $relatedClass,
                'relatedLabel' => ModelName::label($relatedClass),
                'columns'      => $cols,
                'count'        => $relatedCount,
                'sample'       => $sample,
                'workflowUrl'  => url($webPrefix . '/workflow/' . str_replace('\\', '%5C', $relatedClass)),
            ];
        }

        return $panels;
    }

    public function health(Request $request)
    {
        return view('statisty::health', [
            'appName' => config('app.name'),
            'version' => config('statisty.version', '1.0.0'),
            'healthChecks' => $this->healthChecks(),
            ...$this->shellData('health'),
        ]);
    }

    public function logs(Request $request)
    {
        $selectedFile = $request->query('file');
        $logData = $this->logData(is_string($selectedFile) ? $selectedFile : null);

        return view('statisty::logs', [
            'appName' => config('app.name'),
            'version' => config('statisty.version', '1.0.0'),
            'logFiles' => $logData['files'],
            'activeLogFile' => $logData['active'],
            'logEntries' => $logData['entries'],
            ...$this->shellData('logs'),
        ]);
    }

    public function jobs(Request $request)
    {
        return view('statisty::jobs', [
            'appName' => config('app.name'),
            'version' => config('statisty.version', '1.0.0'),
            'jobs' => $this->jobData(),
            ...$this->shellData('jobs'),
        ]);
    }

    private function dashboardModels(): array
    {
        $configured = (array) config('statisty.models', []);
        $models = [];

        foreach ($configured as $model => $options) {
            if (! is_string($model) || $model === '') {
                continue;
            }

            if (is_array($options) && ($options['enabled'] ?? true) === false) {
                continue;
            }

            if (ModelSchema::isQueryableModel($model)) {
                $models[] = ltrim($model, '\\');
            }
        }

        return array_values(array_unique($models));
    }

    private function modelCards(array $models): array
    {
        return array_map(function (string $model): array {
            $columns = array_slice(ModelSchema::visibleColumns($model), 0, 6);
            $rows = $this->recentRows($model, $columns);

            return [
                'class' => $model,
                'label' => ModelName::label($model),
                'columns' => $columns,
                'rows' => $rows,
                'count' => $this->safeCount($model),
                'table_url' => $this->apiUrl('tables', $model),
                'metrics_url' => $this->apiUrl('metrics', $model, ['type' => 'count']),
                'chart_url' => $this->apiUrl('charts', $model),
            ];
        }, $models);
    }

    private function recentRows(string $model, array $columns): array
    {
        if ($columns === [] || ! method_exists($model, 'query')) {
            return [];
        }

        try {
            $query = $model::query()->select($columns)->limit(8);

            if (in_array('created_at', $columns, true)) {
                $query->latest('created_at');
            }

            return $query->get()
                ->map(fn (mixed $row): array => collect($row->toArray())
                    ->only($columns)
                    ->map(fn (mixed $value): mixed => is_scalar($value) || $value === null ? $value : json_encode($value))
                    ->all())
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function safeCount(string $model): int|string
    {
        try {
            return (int) $model::query()->count();
        } catch (\Throwable) {
            return 'Unavailable';
        }
    }

    private function apiUrl(string $endpoint, string $model, array $query = []): string
    {
        $prefix = trim((string) config('statisty.routes.api.prefix', 'api/statisty'), '/');
        $url = url($prefix . '/' . trim($endpoint, '/') . '/' . str_replace('\\', '%5C', $model));

        if ($query === []) {
            return $url;
        }

        return $url . '?' . http_build_query($query);
    }

    private function shellData(string $active): array
    {
        $prefix = trim((string) config('statisty.routes.web.prefix', 'web/statisty'), '/');

        return [
            'activePage' => $active,
            'statistyNav' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => url($prefix . '/dashboard')],
                ['key' => 'health', 'label' => 'Health', 'url' => url($prefix . '/health')],
                ['key' => 'logs', 'label' => 'Logs', 'url' => url($prefix . '/logs')],
                ['key' => 'jobs', 'label' => 'Jobs', 'url' => url($prefix . '/jobs')],
            ],
            'sidebarWorkflows' => array_map(
                fn (string $model): array => [
                    'label' => ModelName::label($model),
                    'class' => $model,
                    'url' => url($prefix . '/workflow/' . str_replace('\\', '%5C', $model)),
                ],
                $this->dashboardModels(),
            ),
        ];
    }

    private function healthChecks(): array
    {
        return [
            $this->healthCheck('Laravel', app()->version(), 'ready'),
            $this->healthCheck('Environment', (string) config('app.env', 'unknown'), config('app.debug') ? 'warning' : 'ready'),
            $this->healthCheck('Debug mode', config('app.debug') ? 'Enabled' : 'Disabled', config('app.debug') ? 'warning' : 'ready'),
            $this->databaseHealth(),
            $this->healthCheck('Cache driver', (string) config('cache.default', 'unknown'), 'ready'),
            $this->healthCheck('Queue connection', (string) config('queue.default', 'sync'), 'ready'),
            $this->storageHealth(),
            $this->healthCheck('Statisty models', (string) count($this->dashboardModels()) . ' active', count($this->dashboardModels()) > 0 ? 'ready' : 'warning'),
        ];
    }

    private function healthCheck(string $label, string $value, string $status = 'ready', ?string $detail = null): array
    {
        return compact('label', 'value', 'status', 'detail');
    }

    private function databaseHealth(): array
    {
        try {
            \DB::connection()->getPdo();

            return $this->healthCheck('Database', (string) config('database.default'), 'ready');
        } catch (\Throwable $exception) {
            return $this->healthCheck('Database', 'Unavailable', 'failed', $exception->getMessage());
        }
    }

    private function storageHealth(): array
    {
        $path = storage_path('logs');

        return $this->healthCheck(
            'Log storage',
            is_writable($path) ? 'Writable' : 'Not writable',
            is_writable($path) ? 'ready' : 'failed',
            $path,
        );
    }

    private function logData(?string $selectedFile = null): array
    {
        $files = collect(glob(storage_path('logs/*.log')) ?: [])
            ->map(fn (string $path): array => [
                'name' => basename($path),
                'path' => $path,
                'size' => filesize($path) ?: 0,
                'updated_at' => date('Y-m-d H:i:s', filemtime($path) ?: time()),
            ])
            ->sortByDesc('updated_at')
            ->values()
            ->all();

        $active = null;
        if ($selectedFile !== null) {
            foreach ($files as $file) {
                if ($file['name'] === $selectedFile) {
                    $active = $file;
                    break;
                }
            }
        }

        if ($active === null) {
            $active = $files[0] ?? null;
        }

        return [
            'files' => $files,
            'active' => $active,
            'entries' => $active === null ? [] : $this->parseLogEntries($active['path']),
        ];
    }

    private function parseLogEntries(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $lines = array_slice(file($path, FILE_IGNORE_NEW_LINES) ?: [], -250);
        $entries = [];

        foreach ($lines as $line) {
            if (trim((string) $line) === '') {
                continue;
            }

            $level = 'info';
            $time = null;
            $message = $line;

            if (preg_match('/^\[(.*?)\]\s+\w+\.(\w+):\s+(.*)$/', $line, $matches) === 1) {
                $time = $matches[1];
                $level = strtolower($matches[2]);
                $message = $matches[3];
            }

            $entries[] = compact('time', 'level', 'message');
        }

        return array_reverse($entries);
    }

    private function jobData(): array
    {
        $hasJobs = \Schema::hasTable('jobs');
        $hasFailed = \Schema::hasTable('failed_jobs');
        $hasBatches = \Schema::hasTable('job_batches');

        return [
            'summary' => [
                'pending' => $hasJobs ? (int) \DB::table('jobs')->whereNull('reserved_at')->count() : 0,
                'running' => $hasJobs ? (int) \DB::table('jobs')->whereNotNull('reserved_at')->count() : 0,
                'executed' => $hasBatches ? (int) \DB::table('job_batches')->sum(\DB::raw('total_jobs - pending_jobs')) : null,
                'failed' => $hasFailed ? (int) \DB::table('failed_jobs')->count() : 0,
            ],
            'tables' => [
                'jobs' => $hasJobs,
                'failed_jobs' => $hasFailed,
                'job_batches' => $hasBatches,
            ],
            'pending' => $hasJobs ? $this->pendingJobs() : [],
            'failed' => $hasFailed ? $this->failedJobs() : [],
            'batches' => $hasBatches ? $this->jobBatches() : [],
        ];
    }

    private function pendingJobs(): array
    {
        return \DB::table('jobs')
            ->orderByDesc('id')
            ->limit(25)
            ->get()
            ->map(fn (object $job): array => [
                'id' => $job->id ?? null,
                'queue' => $job->queue ?? 'default',
                'name' => $this->jobName($job->payload ?? null),
                'attempts' => $job->attempts ?? 0,
                'status' => empty($job->reserved_at) ? 'pending' : 'running',
                'available_at' => $this->timestamp($job->available_at ?? null),
                'created_at' => $this->timestamp($job->created_at ?? null),
            ])
            ->all();
    }

    private function failedJobs(): array
    {
        return \DB::table('failed_jobs')
            ->orderByDesc('id')
            ->limit(25)
            ->get()
            ->map(fn (object $job): array => [
                'id' => $job->id ?? null,
                'uuid' => $job->uuid ?? null,
                'queue' => $job->queue ?? 'default',
                'name' => $this->jobName($job->payload ?? null),
                'failed_at' => $job->failed_at ?? null,
                'exception' => isset($job->exception) ? mb_strimwidth((string) $job->exception, 0, 220, '...') : null,
            ])
            ->all();
    }

    private function jobBatches(): array
    {
        return \DB::table('job_batches')
            ->orderByDesc('created_at')
            ->limit(15)
            ->get()
            ->map(fn (object $batch): array => [
                'id' => $batch->id ?? null,
                'name' => $batch->name ?? 'Batch',
                'total' => $batch->total_jobs ?? 0,
                'pending' => $batch->pending_jobs ?? 0,
                'failed' => $batch->failed_jobs ?? 0,
                'processed' => (int) ($batch->total_jobs ?? 0) - (int) ($batch->pending_jobs ?? 0),
                'created_at' => $this->timestamp($batch->created_at ?? null),
                'finished_at' => $this->timestamp($batch->finished_at ?? null),
            ])
            ->all();
    }

    private function jobName(?string $payload): string
    {
        $data = json_decode((string) $payload, true);

        if (! is_array($data)) {
            return 'Unknown job';
        }

        return (string) ($data['displayName'] ?? $data['job'] ?? 'Queued job');
    }

    private function timestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return date('Y-m-d H:i:s', (int) $value);
        }

        return (string) $value;
    }
}
