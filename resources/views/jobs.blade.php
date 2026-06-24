@extends('statisty::layout')

@section('content')
    <header class="statisty-content-header">
        <div>
            <h1>Queue &amp; Jobs Tracker</h1>
            <p class="statisty-muted">Monitor pending, running, failed, and past background queue processes.</p>
        </div>
    </header>

    @php
        $summary = $jobs['summary'] ?? [];
        $tables  = $jobs['tables'] ?? [];
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

        <!-- ── Tab 1: Current/Pending Jobs ──────────────────────────────── -->
        <div id="pending-jobs-section" class="statisty-tab-content active">
            @if(!$tables['jobs'])
                <div class="statisty-empty-state">
                    <h3>Jobs table not found</h3>
                    <p>The standard Laravel <code>jobs</code> table is not present in your database. Run the migration to enable pending/running jobs tracking:</p>
                    <pre><code>php artisan queue:table &amp;&amp; php artisan migrate</code></pre>
                </div>
            @else
                <div class="jobs-tab-toolbar" id="pending-toolbar">
                    <h2>Active &amp; Queued Jobs</h2>
                    <div class="jobs-pagination-controls">
                        <label>Afficher :</label>
                        <select class="jobs-page-size-select" data-table="pending-table" data-info="pending-info" data-prev="pending-prev" data-next="pending-next" data-indicator="pending-indicator">
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                        </select>
                        <span id="pending-info" class="jobs-info-text"></span>
                        <button id="pending-prev" class="jobs-page-btn" disabled>&#8249;</button>
                        <span id="pending-indicator" class="jobs-page-indicator"></span>
                        <button id="pending-next" class="jobs-page-btn">&#8250;</button>
                    </div>
                </div>

                @if(empty($jobs['pending']))
                    <div class="statisty-table-empty-box">No jobs are currently waiting or processing.</div>
                @else
                    <div class="jobs-table-scroll-wrapper">
                        <table class="statisty-table" id="pending-table">
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
                                    <tr class="jobs-row">
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

        <!-- ── Tab 2: Failed Jobs ───────────────────────────────────────── -->
        <div id="failed-jobs-section" class="statisty-tab-content">
            @if(!$tables['failed_jobs'])
                <div class="statisty-empty-state">
                    <h3>Failed jobs table not found</h3>
                    <p>The standard Laravel <code>failed_jobs</code> table is not present in your database. Run the migration to enable failed jobs tracking:</p>
                    <pre><code>php artisan queue:failed-table &amp;&amp; php artisan migrate</code></pre>
                </div>
            @else
                <div class="jobs-tab-toolbar">
                    <h2>Failed Background Jobs</h2>
                    <div class="jobs-pagination-controls">
                        <label>Afficher :</label>
                        <select class="jobs-page-size-select" data-table="failed-table" data-info="failed-info" data-prev="failed-prev" data-next="failed-next" data-indicator="failed-indicator">
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                        </select>
                        <span id="failed-info" class="jobs-info-text"></span>
                        <button id="failed-prev" class="jobs-page-btn" disabled>&#8249;</button>
                        <span id="failed-indicator" class="jobs-page-indicator"></span>
                        <button id="failed-next" class="jobs-page-btn">&#8250;</button>
                    </div>
                </div>

                @if(empty($jobs['failed']))
                    <div class="statisty-table-empty-box">No failed jobs recorded. Excellent!</div>
                @else
                    <div class="jobs-table-scroll-wrapper">
                        <table class="statisty-table" id="failed-table">
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
                                    <tr class="jobs-row">
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

        <!-- ── Tab 3: Job Batches ───────────────────────────────────────── -->
        <div id="batches-section" class="statisty-tab-content">
            @if(!$tables['job_batches'])
                <div class="statisty-empty-state">
                    <h3>Job batches table not found</h3>
                    <p>The standard Laravel <code>job_batches</code> table is not present in your database. Run the migration to enable batch process monitoring:</p>
                    <pre><code>php artisan queue:batches-table &amp;&amp; php artisan migrate</code></pre>
                </div>
            @else
                <div class="jobs-tab-toolbar">
                    <h2>Job Batches (Past Jobs)</h2>
                    <div class="jobs-pagination-controls">
                        <label>Afficher :</label>
                        <select class="jobs-page-size-select" data-table="batches-table" data-info="batches-info" data-prev="batches-prev" data-next="batches-next" data-indicator="batches-indicator">
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                        </select>
                        <span id="batches-info" class="jobs-info-text"></span>
                        <button id="batches-prev" class="jobs-page-btn" disabled>&#8249;</button>
                        <span id="batches-indicator" class="jobs-page-indicator"></span>
                        <button id="batches-next" class="jobs-page-btn">&#8250;</button>
                    </div>
                </div>

                @if(empty($jobs['batches']))
                    <div class="statisty-table-empty-box">No batch jobs executions found.</div>
                @else
                    <div class="jobs-table-scroll-wrapper">
                        <table class="statisty-table" id="batches-table">
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
                                    @php $percent = $batch['total'] > 0 ? round(($batch['processed'] / $batch['total']) * 100) : 0; @endphp
                                    <tr class="jobs-row">
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

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // ── Tab switching ────────────────────────────────────────────────
            document.querySelectorAll('.statisty-tab-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const targetId = this.getAttribute('data-tab');
                    document.querySelectorAll('.statisty-tab-btn').forEach(b => b.classList.remove('active'));
                    document.querySelectorAll('.statisty-tab-content').forEach(c => c.classList.remove('active'));
                    this.classList.add('active');
                    const target = document.getElementById(targetId);
                    if (target) target.classList.add('active');
                });
            });

            // ── Exception details toggle ─────────────────────────────────────
            document.querySelectorAll('.toggle-exception-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const det = document.getElementById(this.getAttribute('data-target'));
                    if (!det) return;
                    if (det.style.display === 'none') {
                        det.style.display = 'block';
                        this.textContent = 'Hide Error';
                    } else {
                        det.style.display = 'none';
                        this.textContent = 'Show Full Error';
                    }
                });
            });

            // ── Generic table paginator ──────────────────────────────────────
            function initTablePaginator(selectEl) {
                const tableId = selectEl.dataset.table;
                const table   = document.getElementById(tableId);
                if (!table) return;

                const infoEl      = document.getElementById(selectEl.dataset.info);
                const prevBtn     = document.getElementById(selectEl.dataset.prev);
                const nextBtn     = document.getElementById(selectEl.dataset.next);
                const indicatorEl = document.getElementById(selectEl.dataset.indicator);
                const scrollWrap  = table.closest('.jobs-table-scroll-wrapper');

                let rows     = Array.from(table.querySelectorAll('tbody tr.jobs-row'));
                let pageSize = parseInt(selectEl.value);
                let curPage  = 1;

                function render() {
                    const total = rows.length;
                    const pages = Math.max(1, Math.ceil(total / pageSize));
                    if (curPage > pages) curPage = pages;

                    const start = (curPage - 1) * pageSize;
                    const end   = Math.min(start + pageSize, total);

                    rows.forEach((r, i) => { r.style.display = (i >= start && i < end) ? '' : 'none'; });

                    if (scrollWrap) scrollWrap.scrollTop = 0;
                    if (indicatorEl) indicatorEl.textContent = 'Page ' + curPage + ' / ' + pages;
                    if (infoEl) infoEl.textContent = total > 0 ? (start + 1) + '–' + end + ' sur ' + total : '0 ligne';
                    if (prevBtn) prevBtn.disabled = curPage <= 1;
                    if (nextBtn) nextBtn.disabled = curPage >= pages;
                }

                selectEl.addEventListener('change', function () { pageSize = parseInt(this.value); curPage = 1; render(); });
                if (prevBtn) prevBtn.addEventListener('click', () => { if (curPage > 1) { curPage--; render(); } });
                if (nextBtn) nextBtn.addEventListener('click', () => { curPage++; render(); });

                render();
            }

            document.querySelectorAll('.jobs-page-size-select').forEach(initTablePaginator);
        });
    </script>

    <style>
        /* ── Jobs tab toolbar ───────────────────────────────────────────── */
        .jobs-tab-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            padding: 14px 20px 10px;
            border-bottom: 1px solid var(--border-color);
        }
        .jobs-tab-toolbar h2 {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }

        /* ── Pagination controls ───────────────────────────────────────── */
        .jobs-pagination-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .jobs-pagination-controls label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
        }
        .jobs-pagination-controls select {
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-primary);
            font-size: 12px;
            font-family: var(--font-sans);
            cursor: pointer;
        }
        .jobs-info-text {
            font-size: 12px;
            color: var(--text-secondary);
        }
        .jobs-page-btn {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 4px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 700;
            transition: var(--transition-fast);
            font-family: var(--font-sans);
        }
        .jobs-page-btn:hover:not(:disabled) { background: var(--color-primary); color: #fff; border-color: var(--color-primary); }
        .jobs-page-btn:disabled { opacity: 0.3; cursor: default; }
        .jobs-page-indicator {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-primary);
            min-width: 70px;
            text-align: center;
        }

        /* ── Scrollable table wrapper ───────────────────────────────────── */
        .jobs-table-scroll-wrapper {
            overflow-y: auto;
            max-height: 480px;
        }
        .jobs-table-scroll-wrapper::-webkit-scrollbar { width: 6px; }
        .jobs-table-scroll-wrapper::-webkit-scrollbar-track { background: var(--bg-surface); }
        .jobs-table-scroll-wrapper::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }

        /* Sticky header inside scrollable table */
        .jobs-table-scroll-wrapper .statisty-table thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: var(--bg-card);
            box-shadow: 0 1px 0 var(--border-color);
        }
    </style>
@endsection
