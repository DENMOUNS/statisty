<?php
namespace Statisty\Http\Controllers;

use Illuminate\Support\Facades\Cache;

class CacheController extends BaseDashboardController
{
    public function index()
    {
        $default = config('cache.default');
        $store = config("cache.stores.{$default}.driver");
        
        return view('statisty::cache', array_merge($this->shellData('cache'), [
            'default' => $default,
            'driver' => $store,
        ]));
    }

    public function clear()
    {
        try {
            Cache::flush();
            return back()->with('success', 'Cache cleared successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not clear cache: ' . $e->getMessage());
        }
    }
}
