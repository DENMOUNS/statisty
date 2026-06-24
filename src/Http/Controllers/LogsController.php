<?php

declare(strict_types=1);

namespace Statisty\Http\Controllers;

use Illuminate\Http\Request;

final class LogsController extends BaseDashboardController
{
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
}
