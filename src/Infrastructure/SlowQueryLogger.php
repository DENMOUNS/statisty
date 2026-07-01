<?php

declare(strict_types=1);

namespace Statisty\Infrastructure;

use Illuminate\Database\Events\QueryExecuted;

final class SlowQueryLogger
{
    public function listen($app): void
    {
        if (! config('statisty.features.slow_queries.enabled', true)) {
            return;
        }

        if (! $app->bound('db')) {
            return;
        }

        if ($app->runningInConsole() && ! $this->hasDatabaseConnectionConfigured()) {
            return;
        }

        try {
            $db = $app->make('db');
            $db->listen(function (QueryExecuted $query) {
                $threshold = (float) config('statisty.features.slow_queries.threshold_ms', 100);
                if ($query->time < $threshold) {
                    return;
                }

                $sql = $query->sql;
                if (str_contains($sql, 'statisty_slow_queries')) {
                    return;
                }

                $caller = 'Unknown';
                $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
                foreach ($trace as $step) {
                    if (isset($step['file']) && ! str_contains($step['file'], 'vendor\\') && ! str_contains($step['file'], 'vendor/')) {
                        $caller = basename($step['file']) . ':' . $step['line'];
                        break;
                    }
                }

                $bindings = [];
                foreach ($query->bindings as $binding) {
                    if ($binding instanceof \DateTimeInterface) {
                        $bindings[] = $binding->format('Y-m-d H:i:s');
                    } elseif (is_object($binding)) {
                        $bindings[] = get_class($binding);
                    } else {
                        $bindings[] = $binding;
                    }
                }

                $entry = [
                    'sql' => $sql,
                    'bindings' => $bindings,
                    'time_ms' => round($query->time, 2),
                    'caller' => $caller,
                    'connection' => $query->connectionName,
                    'created_at' => date('Y-m-d H:i:s'),
                ];

                $this->appendSlowQuery($entry);
            });
        } catch (\Throwable) {
            // continuer
        }
    }

    private function hasDatabaseConnectionConfigured(): bool
    {
        $connectionName = config('database.default');
        if (! is_string($connectionName) || $connectionName === '') {
            return false;
        }

        $config = config('database.connections.' . $connectionName);
        if (! is_array($config)) {
            return false;
        }

        return ! empty($config['driver']);
    }

    private function appendSlowQuery(array $entry): void
    {
        $filePath = storage_path('logs/statisty_slow_queries.ndjson');
        $lockPath = $filePath . '.lock';
        $maxEntries = 100;

        $lockHandle = @fopen($lockPath, 'c');
        if ($lockHandle === false) {
            return;
        }

        try {
            if (! flock($lockHandle, LOCK_EX)) {
                return;
            }

            $line = json_encode($entry, JSON_UNESCAPED_UNICODE);
            if ($line === false) {
                return;
            }

            $handle = @fopen($filePath, 'a');
            if ($handle === false) {
                return;
            }

            fwrite($handle, $line . "\n");
            fclose($handle);

            try {
                if (random_int(1, 20) === 1) {
                    $this->trimSlowQueryLog($filePath, $maxEntries);
                }
            } catch (\Throwable) {
                // random_int peut théoriquement échouer ; pas critique, on ignore.
            }
        } catch (\Throwable) {
            // continuer
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    private function trimSlowQueryLog(string $filePath, int $maxEntries): void
    {
        if (! file_exists($filePath)) {
            return;
        }

        $lines = @file($filePath, FILE_IGNORE_NEW_LINES) ?: [];
        if (count($lines) <= $maxEntries) {
            return;
        }

        $lines = array_slice($lines, -$maxEntries);

        $tmpPath = $filePath . '.tmp.' . getmypid();
        $written = @file_put_contents($tmpPath, implode("\n", $lines) . "\n");

        if ($written !== false) {
            @rename($tmpPath, $filePath);
        } else {
            @unlink($tmpPath);
        }
    }
}
