<?php

declare(strict_types=1);

namespace Statisty\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class Exporter
{
    public static function streamCsv(Builder $query, Request $request, string $filename = 'statisty_export.csv', int $chunk = 1000)
    {
        $callback = function () use ($query, $chunk, $request) {
            $out = fopen('php://output', 'w');
            $first = true;

            $query->chunk($chunk, function ($rows) use (&$first, $out, $request) {
                foreach ($rows as $row) {
                    $data = (new \Statisty\Http\Resources\TableRowResource($row))->toArray($request);

                    if ($first) {
                        fputcsv($out, array_keys($data));
                        $first = false;
                    }

                    fputcsv($out, array_map(fn($v) => is_array($v) ? implode(', ', $v) : $v, $data));
                }
            });

            fclose($out);
        };

        return response()->streamDownload($callback, $filename, ['Content-Type' => 'text/csv']);
    }
}
