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

        $types = $this->resolveColumnTypes($table, $columns, $schema);

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

    /**
     * Résout les types de colonnes en minimisant les appels coûteux.
    */
    private function resolveColumnTypes(string $table, array $columns, $schema): array
    {
        $types = array_fill_keys($columns, 'unknown');

        // Chemin rapide : Laravel 11+ expose getColumnType() nativement, sans Doctrine.
        if (method_exists($schema, 'getColumnType')) {
            foreach ($columns as $col) {
                try {
                    $types[$col] = $schema->getColumnType($table, $col) ?? 'unknown';
                } catch (\Throwable) {
                    // reste 'unknown'
                }
            }

            return $types;
        }

        // Fallback Doctrine : UN SEUL appel pour toutes les colonnes de la table,
        // au lieu d'un appel getDoctrineColumn() par colonne.
        if (method_exists($this->connection, 'getDoctrineSchemaManager')) {
            try {
                $sm = $this->connection->getDoctrineSchemaManager();
                if (method_exists($sm, 'listTableColumns')) {
                    foreach ($sm->listTableColumns($table) as $name => $doctrineColumn) {
                        if (array_key_exists($name, $types)) {
                            $types[$name] = $doctrineColumn->getType()->getName();
                        }
                    }

                    return $types;
                }
            } catch (\Throwable) {
                // on retente en dernier recours ci-dessous
            }
        }

        // Dernier recours : appel par colonne (seulement si rien d'autre n'est disponible).
        if (method_exists($this->connection, 'getDoctrineColumn')) {
            foreach ($columns as $col) {
                try {
                    $types[$col] = $this->connection->getDoctrineColumn($table, $col)->getType()->getName();
                } catch (\Throwable) {
                    // reste 'unknown'
                }
            }
        }

        return $types;
    }
}