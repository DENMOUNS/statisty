<?php

declare(strict_types=1);

namespace Statisty\Discovery;

use Illuminate\Database\Connection;

final class ModelProfiler
{
    private array $cache = [];

    public function __construct(private Connection $connection)
    {
    }

    public function profile(string $table): array
    {
        if (isset($this->cache[$table])) {
            return $this->cache[$table];
        }

        $schema = $this->connection->getSchemaBuilder();
        $columns = $schema->getColumnListing($table);

        $types = [];
        $timestamps = false;
        $soft = false;

        foreach ($columns as $col) {
            $type = 'unknown';

            try {
                // If connection offers Doctrine helper
                if (method_exists($this->connection, 'getDoctrineColumn')) {
                    $type = $this->connection->getDoctrineColumn($table, $col)->getType()->getName();
                // Some schema builders provide getColumnType
                } elseif (method_exists($schema, 'getColumnType')) {
                    $t = $schema->getColumnType($table, $col);
                    $type = $t ?? 'unknown';
                } else {
                    // Best-effort: try Doctrine SchemaManager if available
                    if (method_exists($this->connection, 'getDoctrineSchemaManager')) {
                        $sm = $this->connection->getDoctrineSchemaManager();
                        if (method_exists($sm, 'listTableColumns')) {
                            $cols = $sm->listTableColumns($table);
                            if (isset($cols[$col])) {
                                $type = $cols[$col]->getType()->getName();
                            }
                        }
                    }
                }
            } catch (\Throwable) {
                $type = 'unknown';
            }

            $types[$col] = $type;
        }

        $timestamps = in_array('created_at', $columns, true) && in_array('updated_at', $columns, true);
        $soft = in_array('deleted_at', $columns, true);

        $profile = [
            'table' => $table,
            'columns' => $columns,
            'types' => $types,
            'timestamps' => $timestamps,
            'softDeletes' => $soft,
        ];

        $this->cache[$table] = $profile;

        return $profile;
    }
}
