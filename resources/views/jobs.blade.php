@extends('statisty::layout')

@section('content')
    <header class="statisty-content-header">
        <div>
            <h1>Queue & Jobs Tracker</h1>
            <p class="statisty-muted">Monitor pending, running, failed, and past background queue processes.</p>
        </div>
    </header>

    @php
        $summary = $jobs['summary'] ?? [];
        $tables = $jobs['tables'] ?? [];
    @endphp

    <!-- KPI Summary Grid -->
    <section class="statisty-kpi-grid" aria-label="Job metrics">
        <article class="statisty-kpi">
            <div class="statisty-kpi-label">Running Jobs</div>
            <div class="statisty-kpi-value">{{ $summary['running'] ?? 0 }}</div>
            <div class="statisty-kpi-meta">
                <span class="statisty-status-badge status-@if(($summary['running'] ?? 0) > 0)ready @elsepending @endif">
                    @if(($summary['running'] ?? 0) > 0) active @else idle @endif
                </span>
            </div>
        </article>

        <article class="statisty-kpi">
            <div class="statisty-kpi-label">Pending Jobs</div>
            <div class="statisty-kpi-value">{{ $summary['pending'] ?? 0 }}</div>
            <div class="statisty-kpi-meta">
                <span class="statisty-status-badge status-pending">queued</span>
            </div>
        </article>

        @if(isset($summary['executed']) && $summary['executed'] !== null)
            <article class="statisty-kpi">
                <div class="statisty-kpi-label">Batch Processed</div>
                <div class="statisty-kpi-value">{{ number_format($summary['executed']) }}</div>
                <div class="statisty-kpi-meta">
                    <span class="statisty-status-badge status-ready">completed</span>
                </div>
            </article>
        @endif

        <article class="statisty-kpi @if(($summary['failed'] ?? 0) > 0) statisty-kpi-danger @endif">
            <div class="statisty-kpi-label">Failed Jobs</div>
            <div class="statisty-kpi-value">{{ $summary['failed'] ?? 0 }}</div>
            <div class="statisty-kpi-meta">
                <span class="statisty-status-badge status-@if(($summary['failed'] ?? 0) > 0)failed @elsepending @endif">
                    @if(($summary['failed'] ?? 0) > 0) attention @else none @endif
                </span>
            </div>
        </article>
    </section>

    <!-- Jobs Tabs & Content -->
    <div class="statisty-jobs-container">
        <!-- Navigation Tabs -->
        <div class="statisty-jobs-tabs">
            <button class="statisty-tab-btn active" data-tab="pending-jobs-section">
                Current Jobs ({{ count($jobs['pending'] ?? []) }})
            </button>
            <button class="statisty-tab-btn" data-tab="failed-jobs-section">
                Failed Jobs ({{ count($jobs['failed'] ?? []) }})
            </button>
            <button class="statisty-tab-btn" data-tab="batches-section">
                Job Batches ({{ count($jobs['batches'] ?? []) }})
            </button>
        </div>

        <!-- Tab 1: Current/Pending Jobs -->
        <div id="pending-jobs-section" class="statisty-tab-content active">
            @if(!$tables['jobs'])
                <div class="statisty-empty-state">
                    <h3>Jobs table not found</h3>
                    <p>The standard Laravel <code>jobs</code> table is not present in your database. Run the migration to enable pending/running jobs tracking:</p>
                    <pre><code>php artisan queue:table && php artisan migrate</code></pre>
                </div>
            @else
                <div class="statisty-table-header-row">
                    <h2>Active & Queued Jobs</h2>
                    <span class="statisty-muted">Showing last 25 entries</span>
                </div>

                @if(empty($jobs['pending']))
                    <div class="statisty-table-empty-box">No jobs are currently waiting or processing.</div>
                @else
                    <div class="statisty-table-wrapper">
                        <table class="statisty-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Job Name</th>
                                    <th>Queue</th>
                                    <th>Status</th>
                                    <th>Attempts</th>
                                    <th>Available At</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($jobs['pending'] as $job)
                                    <tr>
                                        <td><code>#{{ $job['id'] }}</code></td>
                                        <td class="statisty-job-name">{{ $job['name'] }}</td>
                                        <td><span class="statisty-queue-badge">{{ $job['queue'] }}</span></td>
                                        <td>
                                            <span class="statisty-status-badge status-@if($job['status'] === 'running')ready @elsepending @endif">
                                                {{ $job['status'] }}
                                            </span>
                                        </td>
                                        <td>{{ $job['attempts'] }}</td>
                                        <td>{{ $job['available_at'] ?? 'Immediate' }}</td>
                                        <td>{{ $job['created_at'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>

        <!-- Tab 2: Failed Jobs -->
        <div id="failed-jobs-section" class="statisty-tab-content">
            @if(!$tables['failed_jobs'])
                <div class="statisty-empty-state">
                    <h3>Failed jobs table not found</h3>
                    <p>The standard Laravel <code>failed_jobs</code> table is not present in your database. Run the migration to enable failed jobs tracking:</p>
                    <pre><code>php artisan queue:failed-table && php artisan migrate</code></pre>
                </div>
            @else
                <div class="statisty-table-header-row">
                    <h2>Failed Background Jobs</h2>
                    <span class="statisty-muted">Showing last 25 failures</span>
                </div>

                @if(empty($jobs['failed']))
                    <div class="statisty-table-empty-box">No failed jobs recorded. Excellent!</div>
                @else
                    <div class="statisty-table-wrapper">
                        <table class="statisty-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Job Name</th>
                                    <th>Queue</th>
                                    <th>Failed At</th>
                                    <th>Exception Detail</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($jobs['failed'] as $index => $job)
                                    <tr>
                                        <td><code>#{{ $job['id'] }}</code></td>
                                        <td class="statisty-job-name">
                                            <div>{{ $job['name'] }}</div>
                                            <small class="statisty-muted">{{ $job['uuid'] }}</small>
                                        </td>
                                        <td><span class="statisty-queue-badge">{{ $job['queue'] }}</span></td>
                                        <td class="statisty-nowrap">{{ $job['failed_at'] }}</td>
                                        <td>
                                            <div class="statisty-failed-exception">
                                                <div class="exception-summary">
                                                    {{ mb_strimwidth($job['exception'], 0, 100, '...') }}
                                                </div>
                                                @if(strlen($job['exception']) > 100)
                                                    <button class="statisty-btn-link toggle-exception-btn" data-target="exception-{{ $index }}">
                                                        Show Full Error
                                                    </button>
                                                    <div id="exception-{{ $index }}" class="exception-details-pre" style="display: none;">
                                                        <pre><code>{{ $job['exception'] }}</code></pre>
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>

        <!-- Tab 3: Job Batches -->
        <div id="batches-section" class="statisty-tab-content">
            @if(!$tables['job_batches'])
                <div class="statisty-empty-state">
                    <h3>Job batches table not found</h3>
                    <p>The standard Laravel <code>job_batches</code> table is not present in your database. Run the migration to enable batch process monitoring:</p>
                    <pre><code>php artisan queue:batches-table && php artisan migrate</code></pre>
                </div>
            @else
                <div class="statisty-table-header-row">
                    <h2>Job Batches (Past Jobs)</h2>
                    <span class="statisty-muted">Showing last 15 batch executions</span>
                </div>

                @if(empty($jobs['batches']))
                    <div class="statisty-table-empty-box">No batch jobs executions found.</div>
                @else
                    <div class="statisty-table-wrapper">
                        <table class="statisty-table">
                            <thead>
                                <tr>
                                    <th>Batch ID</th>
                                    <th>Name</th>
                                    <th>Total Jobs</th>
                                    <th>Processed</th>
                                    <th>Failed</th>
                                    <th>Progress</th>
                                    <th>Created At</th>
                                    <th>Finished At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($jobs['batches'] as $batch)
                                    @php
                                        $percent = $batch['total'] > 0 ? round(($batch['processed'] / $batch['total']) * 100) : 0;
                                    @endphp
                                    <tr>
                                        <td><code>{{ mb_strimwidth($batch['id'], 0, 8, '...') }}</code></td>
                                        <td><strong>{{ $batch['name'] }}</strong></td>
                                        <td>{{ $batch['total'] }}</td>
                                        <td>{{ $batch['processed'] }}</td>
                                        <td>
                                            @if($batch['failed'] > 0)
                                                <span class="statisty-status-badge status-failed">{{ $batch['failed'] }}</span>
                                            @else
                                                0
                                            @endif
                                        </td>
                                        <td>
                                            <div class="statisty-progress-bar-wrapper">
                                                <div class="statisty-progress-bar" style="width: {{ $percent }}%;"></div>
                                                <span class="statisty-progress-label">{{ $percent }}%</span>
                                            </div>
                                        </td>
                                        <td class="statisty-nowrap">{{ $batch['created_at'] }}</td>
                                        <td class="statisty-nowrap">{{ $batch['finished_at'] ?? 'Running...' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>
    </div>

    <!-- Inline Script for Tab Navigation and Exception Details Toggle -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Tab switching logic
            const tabButtons = document.querySelectorAll('.statisty-tab-btn');
            const tabContents = document.querySelectorAll('.statisty-tab-content');

            tabButtons.forEach(btn => {
                btn.addEventListener('click', function () {
                    const targetId = this.getAttribute('data-tab');

                    tabButtons.forEach(b => b.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));

                    this.classList.add('active');
                    const targetContent = document.getElementById(targetId);
                    if (targetContent) {
                        targetContent.classList.add('active');
                    }
                });
            });

            // Exception details toggle logic
            const toggleButtons = document.querySelectorAll('.toggle-exception-btn');
            toggleButtons.forEach(btn => {
                btn.addEventListener('click', function () {
                    const targetId = this.getAttribute('data-target');
                    const detailsDiv = document.getElementById(targetId);

                    if (detailsDiv) {
                        if (detailsDiv.style.display === 'none') {
                            detailsDiv.style.display = 'block';
                            this.textContent = 'Hide Error';
                        } else {
                            detailsDiv.style.display = 'none';
                            this.textContent = 'Show Full Error';
                        }
                    }
                });
            });
        });
    </script>
@endsection
