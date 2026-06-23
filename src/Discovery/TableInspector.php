<?php

declare(strict_types=1);

namespace Statisty\Discovery;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;

final class TableInspector
{
    private array $sensitive = ['password', 'remember_token', 'tokens', 'secret', 'secrets'];

    public function __construct(private Connection $connection)
    {
    }

    public function columnsForModel(string $modelClass): array
    {
        if (! class_exists($modelClass)) {
            throw new \InvalidArgumentException("Model class [{$modelClass}] not found.");
        }

        $model = new $modelClass();

        if (! $model instanceof Model) {
            return [];
        }

        $table = $model->getTable();

        $columns = $this->connection->getSchemaBuilder()->getColumnListing($table);

        return array_values(array_filter($columns, fn($c) => ! in_array($c, $this->sensitive, true)));
    }
}
