<?php

declare(strict_types=1);

namespace Statisty\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class JobsController extends BaseDashboardController
{
    public function jobs(Request $request): View|JsonResponse
    {
        return view('statisty::jobs', [
            'appName' => config('app.name'),
            'version' => config('statisty.version', '1.0.0'),
            'jobs' => $this->jobData(),
            ...$this->shellData('jobs'),
        ]);
    }

    private function jobData(): array
    {
        $hasJobs = Schema::hasTable('jobs');
        $hasFailed = Schema::hasTable('failed_jobs');
        $hasBatches = Schema::hasTable('job_batches');

        return [
            'summary' => [
                'pending' => $hasJobs ? (int) DB::table('jobs')->whereNull('reserved_at')->count() : 0,
                'running' => $hasJobs ? (int) DB::table('jobs')->whereNotNull('reserved_at')->count() : 0,
                'executed' => $hasBatches ? (int) DB::table('job_batches')->sum(DB::raw('total_jobs - pending_jobs')) : null,
                'failed' => $hasFailed ? (int) DB::table('failed_jobs')->count() : 0,
            ],
            'tables' => [
                'jobs' => $hasJobs,
                'failed_jobs' => $hasFailed,
                'job_batches' => $hasBatches,
            ],
            'pending' => $hasJobs ? $this->pendingJobs() : [],
            'failed' => $hasFailed ? $this->failedJobs() : [],
            'batches' => $hasBatches ? $this->jobBatches() : [],
        ];
    }

    private function pendingJobs(): array
    {
        return DB::table('jobs')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get()
            ->map(fn (object $job): array => [
                'id' => $job->id ?? null,
                'queue' => $job->queue ?? 'default',
                'name' => $this->jobName($job->payload ?? null),
                'attempts' => $job->attempts ?? 0,
                'status' => empty($job->reserved_at) ? 'pending' : 'running',
                'available_at' => $this->timestamp($job->available_at ?? null),
                'created_at' => $this->timestamp($job->created_at ?? null),
            ])
            ->all();
    }

    private function failedJobs(): array
    {
        return DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(25)
            ->get()
            ->map(fn (object $job): array => [
                'id' => $job->id ?? null,
                'uuid' => $job->uuid ?? null,
                'queue' => $job->queue ?? 'default',
                'name' => $this->jobName($job->payload ?? null),
                'failed_at' => $job->failed_at ?? null,
                'exception' => isset($job->exception) ? mb_strimwidth((string) $job->exception, 0, 220, '...') : null,
            ])
            ->all();
    }

    private function jobBatches(): array
    {
        return DB::table('job_batches')
            ->orderByDesc('created_at')
            ->limit(15)
            ->get()
            ->map(fn (object $batch): array => [
                'id' => $batch->id ?? null,
                'name' => $batch->name ?? 'Batch',
                'total' => $batch->total_jobs ?? 0,
                'pending' => $batch->pending_jobs ?? 0,
                'failed' => $batch->failed_jobs ?? 0,
                'processed' => (int) ($batch->total_jobs ?? 0) - (int) ($batch->pending_jobs ?? 0),
                'created_at' => $this->timestamp($batch->created_at ?? null),
                'finished_at' => $this->timestamp($batch->finished_at ?? null),
            ])
            ->all();
    }

    private function jobName(?string $payload): string
    {
        $data = json_decode((string) $payload, true);

        if (! is_array($data)) {
            return 'Unknown job';
        }

        return (string) ($data['displayName'] ?? $data['job'] ?? 'Queued job');
    }
}
