<?php
declare(strict_types=1);

namespace Statisty\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Statisty\Support\ModelSchema;

class ModelsController extends BaseDashboardController
{
    // Laravel DB column types → Eloquent cast types
    private const CAST_MAP = [
        'int'       => 'integer',
        'integer'   => 'integer',
        'tinyint'   => 'boolean',
        'bigint'    => 'integer',
        'smallint'  => 'integer',
        'float'     => 'float',
        'double'    => 'float',
        'decimal'   => 'decimal:2',
        'varchar'   => 'string',
        'char'      => 'string',
        'text'      => 'string',
        'longtext'  => 'string',
        'mediumtext'=> 'string',
        'json'      => 'array',
        'date'      => 'date',
        'datetime'  => 'datetime',
        'timestamp' => 'datetime',
        'boolean'   => 'boolean',
        'bool'      => 'boolean',
        'uuid'      => 'string',
        'ulid'      => 'string',
    ];

    public function index()
    {
        $models = $this->dashboardModels();
        $details = [];
        foreach ($models as $model) {
            try {
                $instance = new $model;
                $table = $instance->getTable();
                $columnNames = ModelSchema::columns($model);
                $columnsWithTypes = $this->getColumnsWithTypes($table, $columnNames);

                // Get existing fillable / casts from the model
                $existingFillable = $instance->getFillable();
                $existingCasts    = method_exists($instance, 'getCasts') ? $instance->getCasts() : [];

                $details[] = [
                    'class'            => $model,
                    'short'            => class_basename($model),
                    'table'            => $table,
                    'count'            => $model::query()->count(),
                    'columns_count'    => count($columnNames),
                    'columns'          => $columnsWithTypes,
                    'fillable'         => $existingFillable,
                    'casts'            => $existingCasts,
                ];
            } catch (\Throwable) {}
        }

        return view('statisty::models', array_merge($this->shellData('models'), [
            'models'   => $details,
            'allModels'=> $models,
        ]));
    }

    // ─── Create new Model + Migration ────────────────────────────────────────

    public function store(Request $request)
    {
        $modelName = trim($request->input('model_name', ''));
        $fields    = $request->input('fields', []);

        if (!$modelName) return back()->with('error', 'Le nom du modèle est requis.');

        try {
            Artisan::call('make:model', ['name' => $modelName, '-m' => true]);

            if (!empty($fields)) {
                $tableName     = Str::snake(Str::pluralStudly($modelName));
                $migrationFile = $this->findMigrationFile("create_{$tableName}_table");

                if ($migrationFile) {
                    $this->injectColumnsIntoMigration($migrationFile, $fields, '$table->id();', true);
                }

                $modelFile = app_path("Models/{$modelName}.php");
                if (file_exists($modelFile)) {
                    $this->injectFillableIntoModel($modelFile, array_column($fields, 'name'));
                }
            }

            return back()->with('success', "Modèle <strong>{$modelName}</strong> et sa migration générés avec succès !");
        } catch (\Throwable $e) {
            return back()->with('error', 'Erreur : ' . $e->getMessage());
        }
    }

    // ─── Modify existing Model (fillable / casts) ─────────────────────────

    public function modifyModel(Request $request)
    {
        $className = $request->input('class_name', '');
        $mode      = $request->input('mode', 'fillable'); // 'fillable' | 'casts'
        $selected  = $request->input('selected', []);     // array of column names

        if (!class_exists($className)) return back()->with('error', 'Classe introuvable.');

        try {
            $reflection = new \ReflectionClass($className);
            $filePath   = $reflection->getFileName();
            $content    = file_get_contents($filePath);

            // Strip old declarations
            $content = preg_replace('/\n?\s{4}protected\s+\$fillable\s*=\s*\[.*?\];\s*\n/s', "\n", $content);
            $content = preg_replace('/\n?\s{4}protected\s+\$casts\s*=\s*\[.*?\];\s*\n/s', "\n", $content);

            $inject = '';

            if ($mode === 'fillable' && !empty($selected)) {
                $list    = "'" . implode("', '", $selected) . "'";
                $inject .= "\n    protected \$fillable = [{$list}];\n";
            }

            if ($mode === 'casts' && !empty($selected)) {
                // selected = ['field:casttype', ...]
                $pairs = [];
                foreach ($selected as $item) {
                    if (str_contains($item, ':')) {
                        [$col, $cast] = explode(':', $item, 2);
                        $pairs[] = "        '{$col}' => '{$cast}'";
                    }
                }
                if ($pairs) {
                    $inject .= "\n    protected \$casts = [\n" . implode(",\n", $pairs) . "\n    ];\n";
                }
            }

            // Insert right after the opening class brace
            $content = preg_replace(
                '/(class\s+\w[\w\d_]*(?:\s+extends\s+[\w\\\\ ]+)?(?:\s+implements\s+[\w\\\\ ,]+)?\s*\{)/',
                "$1{$inject}",
                $content,
                1
            );

            file_put_contents($filePath, $content);
            return back()->with('success', 'Modèle mis à jour avec succès !');
        } catch (\Throwable $e) {
            return back()->with('error', 'Erreur : ' . $e->getMessage());
        }
    }

    // ─── Alter existing table (new migration) ────────────────────────────

    public function alter(Request $request)
    {
        $tableName = trim($request->input('table_name', ''));
        $fields    = $request->input('fields', []);

        if (!$tableName || empty($fields)) return back()->with('error', 'Table et champs requis.');

        $fields = array_filter($fields, fn($f) => !empty(trim($f['name'] ?? '')));
        if (empty($fields)) return back()->with('error', 'Aucun champ valide fourni.');

        try {
            $migrationName = 'add_fields_to_' . $tableName . '_table';
            Artisan::call('make:migration', ['name' => $migrationName, '--table' => $tableName]);

            $migrationFile = $this->findMigrationFile($migrationName);

            if ($migrationFile) {
                $upCode   = '';
                $downCode = '';
                foreach ($fields as $field) {
                    $name = trim($field['name']);
                    $type = $field['type'];
                    if ($type === 'enum') {
                        $vals    = array_map('trim', explode(',', $field['enum_values'] ?? ''));
                        $valsStr = "['" . implode("', '", $vals) . "']";
                        $upCode .= "            \$table->enum('{$name}', {$valsStr})->nullable();\n";
                    } else {
                        $upCode .= "            \$table->{$type}('{$name}')->nullable();\n";
                    }
                    $downCode .= "            \$table->dropColumn('{$name}');\n";
                }

                $content = file_get_contents($migrationFile);
                // Inject up
                $content = preg_replace(
                    '/(Schema::table\s*\(\s*[\'"]' . $tableName . '[\'"]\s*,\s*function\s*\(Blueprint\s*\$table\)\s*\{)/',
                    "$1\n{$upCode}",
                    $content,
                    1
                );
                // Split on down() and inject rollback
                $parts = preg_split('/(public\s+function\s+down\s*\()/', $content, 2, PREG_SPLIT_DELIM_CAPTURE);
                if (count($parts) === 3) {
                    $downContent = preg_replace(
                        '/(Schema::table\s*\(\s*[\'"]' . $tableName . '[\'"]\s*,\s*function\s*\(Blueprint\s*\$table\)\s*\{)/',
                        "$1\n{$downCode}",
                        $parts[2],
                        1
                    );
                    $content = $parts[0] . $parts[1] . $parts[2];
                    $content = $parts[0] . 'public function down(' . $downContent;
                }
                file_put_contents($migrationFile, $content);
            }

            return back()->with('success', "Migration d'altération pour <strong>{$tableName}</strong> générée avec succès !");
        } catch (\Throwable $e) {
            return back()->with('error', 'Erreur : ' . $e->getMessage());
        }
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function getColumnsWithTypes(string $table, array $columnNames): array
    {
        $result = [];
        try {
            $connection = \Illuminate\Support\Facades\DB::connection();
            $sm = $connection->getDoctrineSchemaManager();
            $columns = $sm->listTableColumns($table);
            foreach ($columnNames as $name) {
                $dbType = isset($columns[$name])
                    ? strtolower($columns[$name]->getType()->getName())
                    : 'string';
                $result[] = [
                    'name'    => $name,
                    'db_type' => $dbType,
                    'cast'    => self::CAST_MAP[$dbType] ?? 'string',
                ];
            }
        } catch (\Throwable) {
            foreach ($columnNames as $name) {
                $result[] = ['name' => $name, 'db_type' => 'string', 'cast' => 'string'];
            }
        }
        return $result;
    }

    private function findMigrationFile(string $needle): ?string
    {
        $path  = database_path('migrations');
        $files = scandir($path);
        rsort($files);
        foreach ($files as $file) {
            if (str_contains($file, $needle) && str_ends_with($file, '.php')) {
                return $path . '/' . $file;
            }
        }
        return null;
    }

    private function injectColumnsIntoMigration(string $file, array $fields, string $anchor, bool $create = false): void
    {
        $content = file_get_contents($file);
        $cols = '';
        foreach ($fields as $field) {
            $name = trim($field['name'] ?? '');
            $type = $field['type'] ?? 'string';
            if (!$name) continue;
            if ($type === 'enum') {
                $vals    = array_map('trim', explode(',', $field['enum_values'] ?? ''));
                $valsStr = "['" . implode("', '", $vals) . "']";
                $cols   .= "            \$table->enum('{$name}', {$valsStr});\n";
            } else {
                $cols .= "            \$table->{$type}('{$name}');\n";
            }
        }
        $content = str_replace($anchor, $anchor . "\n" . $cols, $content);
        file_put_contents($file, $content);
    }

    private function injectFillableIntoModel(string $file, array $names): void
    {
        $names   = array_map('trim', array_filter($names));
        $list    = "'" . implode("', '", $names) . "'";
        $content = file_get_contents($file);
        $inject  = "\n    protected \$fillable = [{$list}];\n";
        $content = preg_replace('/use HasFactory;\n/', "use HasFactory;\n{$inject}", $content, 1);
        file_put_contents($file, $content);
    }
}
