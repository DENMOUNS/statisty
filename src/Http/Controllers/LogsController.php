<?php

declare(strict_types=1);

namespace Statisty\Http\Controllers;

use Illuminate\Http\Request;

final class LogsController extends BaseDashboardController
{
    /**
     * Nombre maximum de lignes conservées en mémoire.
     * Augmentez si vous voulez plus d'historique — la consommation mémoire
     * reste proportionnelle à ce chiffre, pas à la taille du fichier.
     */
    private const MAX_LINES = 500;

    public function logs(Request $request)
    {
        $selectedFile = $request->query('file');
        $logData      = $this->logData(is_string($selectedFile) ? $selectedFile : null);

        return view('statisty::logs', [
            'appName'       => config('app.name'),
            'version'       => config('statisty.version', '1.0.0'),
            'logFiles'      => $logData['files'],
            'activeLogFile' => $logData['active'],
            'logEntries'    => $logData['entries'],
            ...$this->shellData('logs'),
        ]);
    }

    private function logData(?string $selectedFile = null): array
    {
        $files = collect(glob(storage_path('logs/*.log')) ?: [])
            ->map(fn (string $path): array => [
                'name'       => basename($path),
                'path'       => $path,
                'size'       => filesize($path) ?: 0,
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
            'files'   => $files,
            'active'  => $active,
            'entries' => $active === null ? [] : $this->parseLogEntries($active['path']),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FIX 2b : lecture des N dernières lignes sans charger tout le fichier.
    //
    // Avant  : file($path) → charge l'intégralité en mémoire (fatal sur 500 MB)
    // Après  : SplFileObject::seek() vers la fin + lecture en remontant ligne
    //          par ligne → empreinte mémoire proportionnelle à MAX_LINES seulement.
    // ─────────────────────────────────────────────────────────────────────────
    private function parseLogEntries(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $lines = $this->tailFile($path, self::MAX_LINES);

        $entries = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $level   = 'info';
            $time    = null;
            $message = $line;

            if (preg_match('/^\[(.*?)\]\s+\w+\.(\w+):\s+(.*)$/s', $line, $matches) === 1) {
                $time    = $matches[1];
                $level   = strtolower($matches[2]);
                $message = $matches[3];
            }

            $entries[] = compact('time', 'level', 'message');
        }

        return array_reverse($entries);
    }

    /**
     * Retourne les $n dernières lignes d'un fichier en utilisant SplFileObject.
     * La lecture se fait en remontant depuis la fin → O(n) en mémoire,
     * indépendamment de la taille totale du fichier.
     *
     * @return string[]
     */
    private function tailFile(string $path, int $n): array
    {
        try {
            $file = new \SplFileObject($path, 'r');
            $file->seek(PHP_INT_MAX); // positionne à la dernière ligne (seek() avec PHP_INT_MAX)
            $totalLines = $file->key(); // numéro de la dernière ligne (0-indexé)

            $startLine = max(0, $totalLines - $n);
            $file->seek($startLine);

            $lines = [];
            while (! $file->eof()) {
                $lines[] = $file->current();
                $file->next();
            }

            return $lines;
        } catch (\Throwable $e) {
            // Fallback gracieux si SplFileObject n'est pas disponible
            return array_slice(
                @file($path, FILE_IGNORE_NEW_LINES) ?: [],
                -$n,
            );
        }
    }
}
