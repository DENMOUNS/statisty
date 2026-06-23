<?php

declare(strict_types=1);

namespace Statisty\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Statisty\Discovery\ModelProfiler;
use Statisty\Discovery\CachedModelProfiler;
use Statisty\Cache\ProfilingCache;
use Statisty\Tests\TestCase;

class CachedModelProfilerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('profiles');
        parent::tearDown();
    }

    public function test_cached_profiler_writes_to_cache()
    {
        $conn = \DB::connection();
        $prof = new ModelProfiler($conn);
        $cache = new ProfilingCache(cache()->store());

        $cached = new CachedModelProfiler($prof, $cache);

        $res = $cached->profile('profiles');

        $this->assertArrayHasKey('table', $res);

        $key = $cache->key('profiles');
        $this->assertTrue(cache()->has($key));
    }
}
