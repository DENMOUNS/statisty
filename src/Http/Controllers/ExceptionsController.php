<?php
namespace Statisty\Http\Controllers;

class ExceptionsController extends BaseDashboardController
{
    public function index()
    {
        $logPath = storage_path('logs/laravel.log');
        $exceptions = [];
        if (file_exists($logPath)) {
            $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_reverse($lines);
            foreach ($lines as $line) {
                if (str_contains($line, '.ERROR:') || str_contains($line, 'Exception')) {
                    $exceptions[] = $line;
                }
                if (count($exceptions) > 1000) break;
            }
        }
        return view('statisty::exceptions', array_merge($this->shellData('exceptions'), [
            'exceptions' => $exceptions,
        ]));
    }
}
