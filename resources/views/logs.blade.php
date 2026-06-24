@extends('statisty::layout')

@section('content')
    <header class="statisty-content-header">
        <div>
            <h1>Log Viewer</h1>
            <p class="statisty-muted">Real-time application execution and error logs diagnostics.</p>
        </div>
    </header>

    @if(empty($logFiles))
        <section class="statisty-empty">
            <h2>No log files found</h2>
            <p>Make sure your application is configured to write logs to <code>storage/logs/</code>.</p>
        </section>
    @else
        <div class="statisty-logviewer-layout">
            <!-- Sidebar with log files -->
            <aside class="statisty-logviewer-sidebar">
                <span class="statisty-sidebar-title">Log Files</span>
                <div class="statisty-log-files-list">
                    @foreach($logFiles as $file)
                        @php
                            $isSelected = ($activeLogFile['name'] ?? '') === $file['name'];
                            $sizeKb = round(($file['size'] ?? 0) / 1024, 2);
                            $sizeDisplay = $sizeKb > 1024 ? round($sizeKb / 1024, 2) . ' MB' : $sizeKb . ' KB';
                        @endphp
                        <a href="?file={{ urlencode($file['name']) }}" class="statisty-log-file-item @if($isSelected) active @endif">
                            <div class="statisty-log-file-name">{{ $file['name'] }}</div>
                            <div class="statisty-log-file-meta">
                                <span>{{ $sizeDisplay }}</span>
                                <span>&bull;</span>
                                <span>{{ date('H:i:s d/m/Y', strtotime($file['updated_at'])) }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </aside>

            <!-- Main area with logs list -->
            <main class="statisty-logviewer-main">
                <!-- Log Filters Bar -->
                <div class="statisty-logviewer-filters">
                    <div class="statisty-search-wrapper">
                        <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" id="statistyLogSearch" placeholder="Search log messages..." aria-label="Search logs">
                    </div>
                    <div class="statisty-filters-group">
                        <select id="statistyLogLevelFilter" aria-label="Filter by level">
                            <option value="all">All Levels</option>
                            <option value="emergency">Emergency</option>
                            <option value="alert">Alert</option>
                            <option value="critical">Critical</option>
                            <option value="error">Error</option>
                            <option value="warning">Warning</option>
                            <option value="notice">Notice</option>
                            <option value="info">Info</option>
                            <option value="debug">Debug</option>
                        </select>
                        <button id="statistyLogExpandAll" class="statisty-btn-secondary">Expand All</button>
                    </div>
                </div>

                <!-- Active Log Info -->
                <div class="statisty-active-log-info">
                    <span class="active-file-label">Active file:</span>
                    <span class="active-file-name">{{ $activeLogFile['name'] ?? 'None' }}</span>
                </div>

                <!-- Log entries container -->
                <div class="statisty-log-entries" id="statistyLogEntries">
                    @forelse($logEntries as $index => $entry)
                        @php
                            $level = strtolower($entry['level'] ?? 'info');
                            $hasStackTrace = preg_match('/Stack trace:/', $entry['message']) || preg_match('/#\d+\s+/', $entry['message']);
                        @endphp
                        <article class="statisty-log-entry level-{{ $level }}" data-level="{{ $level }}">
                            <div class="statisty-log-entry-header">
                                <div class="statisty-log-entry-meta">
                                    <span class="statisty-log-level-badge level-{{ $level }}">{{ strtoupper($level) }}</span>
                                    <span class="statisty-log-time">{{ $entry['time'] ?? 'Unknown Time' }}</span>
                                </div>
                                @if($hasStackTrace)
                                    <button class="statisty-log-toggle-trace">Voir la trace</button>
                                @endif
                            </div>
                            <div class="statisty-log-entry-body">
                                <span class="statisty-log-message">{{ $entry['message'] }}</span>
                            </div>
                        </article>
                    @empty
                        <div class="statisty-log-entries-empty">Aucune entrée dans ce fichier.</div>
                    @endforelse
                </div>

                <!-- Pagination bar -->
                <div class="log-pagination-bar" id="logPaginationBar">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <label style="font-size:12px;font-weight:600;color:rgba(255,255,255,0.7);">Afficher :</label>
                        <select id="logPageSize" style="padding:4px 8px;border-radius:6px;border:1px solid rgba(255,255,255,0.2);background:rgba(255,255,255,0.1);color:#fff;font-size:12px;font-family:var(--font-sans);">
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                        </select>
                        <span id="logPaginationInfo" style="font-size:12px;color:rgba(255,255,255,0.6);"></span>
                    </div>
                    <div style="display:flex;gap:4px;">
                        <button id="logPrevBtn" class="log-page-btn" disabled>&#8249;</button>
                        <span id="logPageIndicator" style="font-size:12px;font-weight:600;color:#fff;padding:5px 10px;"></span>
                        <button id="logNextBtn" class="log-page-btn">&#8250;</button>
                    </div>
                </div>
            </main>
        </div>
    @endif


    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput      = document.getElementById('statistyLogSearch');
            const levelSelect      = document.getElementById('statistyLogLevelFilter');
            const entriesContainer = document.getElementById('statistyLogEntries');
            const pageSizeEl       = document.getElementById('logPageSize');
            const prevBtn          = document.getElementById('logPrevBtn');
            const nextBtn          = document.getElementById('logNextBtn');
            const pageIndicator    = document.getElementById('logPageIndicator');
            const paginationInfo   = document.getElementById('logPaginationInfo');

            let allEntries    = entriesContainer ? Array.from(entriesContainer.getElementsByClassName('statisty-log-entry')) : [];
            let visibleEntries = [...allEntries];
            let currentPage   = 1;
            let pageSize      = 25;

            function filterLogs() {
                const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
                const level = levelSelect ? levelSelect.value : 'all';
                visibleEntries = allEntries.filter(function(e) {
                    var el = e.getAttribute('data-level');
                    var msg = e.querySelector('.statisty-log-message');
                    var matchLvl = (level === 'all' || el === level);
                    var matchSrc = (query === '' || (msg && msg.textContent.toLowerCase().includes(query)));
                    return matchLvl && matchSrc;
                });
                currentPage = 1;
                renderPage();
            }

            function renderPage() {
                var total = visibleEntries.length;
                var pages = Math.max(1, Math.ceil(total / pageSize));
                if (currentPage > pages) currentPage = pages;

                allEntries.forEach(function(e) { e.style.display = 'none'; });
                var start = (currentPage - 1) * pageSize;
                var end   = Math.min(start + pageSize, total);
                for (var i = start; i < end; i++) {
                    if (visibleEntries[i]) visibleEntries[i].style.display = 'block';
                }
                if (pageIndicator)  pageIndicator.textContent  = 'Page ' + currentPage + ' / ' + pages;
                if (paginationInfo) paginationInfo.textContent  = (start + 1) + '-' + end + ' sur ' + total + ' entrées';
                if (prevBtn) prevBtn.disabled = currentPage <= 1;
                if (nextBtn) nextBtn.disabled = currentPage >= pages;
            }

            if (searchInput) searchInput.addEventListener('input', filterLogs);
            if (levelSelect) levelSelect.addEventListener('change', filterLogs);
            if (pageSizeEl)  pageSizeEl.addEventListener('change', function() { pageSize = parseInt(this.value); currentPage = 1; renderPage(); });
            if (prevBtn)     prevBtn.addEventListener('click', function() { if (currentPage > 1) { currentPage--; renderPage(); } });
            if (nextBtn)     nextBtn.addEventListener('click', function() { currentPage++; renderPage(); });

            // Toggle stack traces
            document.querySelectorAll('.statisty-log-toggle-trace').forEach(function(button) {
                button.addEventListener('click', function () {
                    var entry = this.closest('.statisty-log-entry');
                    var body  = entry.querySelector('.statisty-log-entry-body');
                    body.classList.toggle('expanded');
                    this.textContent = body.classList.contains('expanded') ? 'Réduire' : 'Voir la trace';
                });
            });

            // Expand all
            var expandAllBtn = document.getElementById('statistyLogExpandAll');
            if (expandAllBtn) {
                expandAllBtn.addEventListener('click', function () {
                    var bodies = document.querySelectorAll('.statisty-log-entry-body');
                    var anyCollapsed = Array.from(bodies).some(function(b) { return !b.classList.contains('expanded'); });
                    bodies.forEach(function(body) {
                        var btn = body.closest('.statisty-log-entry').querySelector('.statisty-log-toggle-trace');
                        if (anyCollapsed) { body.classList.add('expanded'); if (btn) btn.textContent = 'Réduire'; }
                        else { body.classList.remove('expanded'); if (btn) btn.textContent = 'Voir la trace'; }
                    });
                    this.textContent = anyCollapsed ? 'Tout réduire' : 'Tout développer';
                });
            }

            renderPage();
        });
    </script>

    <style>
        .log-pagination-bar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 16px;
            background: rgba(255,255,255,0.05);
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .log-page-btn {
            background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
            color: #fff; padding: 5px 12px; border-radius: 6px; cursor: pointer;
            font-size: 16px; font-weight: 700; transition: var(--transition-fast);
            font-family: var(--font-sans);
        }
        .log-page-btn:hover:not(:disabled) { background: rgba(255,255,255,0.2); }
        .log-page-btn:disabled { opacity: 0.3; cursor: default; }
    </style>
@endsection

