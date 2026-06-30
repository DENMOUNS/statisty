<?php

declare(strict_types=1);

namespace Statisty\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class Exporter
{
    private const DEFAULT_MAX_ROWS = 100_000;

    public static function streamCsv(
        Builder $query,
        Request $request,
        string $filename = 'statisty_export.csv',
        int $chunk = 1000,
    ) {
        $maxRows = (int) config('statisty.export.max_rows', self::DEFAULT_MAX_ROWS);

        if ($maxRows > 0) {
            try {
                $total = (clone $query)->count();
            } catch (\Throwable $e) {
                $total = 0;
            }

            if ($total > $maxRows) {
                return response()->json([
                    'error'   => 'export_too_large',
                    'message' => "L'export dépasse la limite de {$maxRows} lignes ({$total} lignes trouvées). "
                        . "Affinez vos filtres ou augmentez statisty.export.max_rows.",
                    'total'   => $total,
                    'limit'   => $maxRows,
                ], 422);
            }
        }

        $callback = function () use ($query, $chunk, $request, $maxRows) {
            $out      = fopen('php://output', 'w');
            $first    = true;
            $exported = 0;
            $stopped  = false;

            $query->chunk($chunk, function ($rows) use (&$first, &$exported, &$stopped, $out, $request, $maxRows) {
                if ($stopped) {
                    return false; // arrête le chunk() proprement
                }

                foreach ($rows as $row) {
                    $data = (new \Statisty\Http\Resources\TableRowResource($row))->toArray($request);

                    // En-têtes CSV sur la première ligne
                    if ($first) {
                        fputcsv($out, array_keys($data));
                        $first = false;
                    }

                    fputcsv($out, array_map(
                        fn ($v) => is_array($v) ? implode(', ', $v) : $v,
                        $data,
                    ));

                    $exported++;

                    // Si des lignes ont été insérées entre le count() et le stream
                    if ($maxRows > 0 && $exported >= $maxRows) {
                        $stopped = true;

                        return false; // arrête le chunk()
                    }
                }
            });

            fclose($out);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
