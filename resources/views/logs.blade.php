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
            <!-- Sidebar with log files — fixed height, scrollable -->
            <aside class="statisty-logviewer-sidebar">
                <span class="statisty-sidebar-title">Log Files <span class="lf-count">({{ count($logFiles) }})</span></span>
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
                            <option value="all" @if(($activeLevel ?? 'all') === 'all') selected @endif>All Levels</option>
                            <option value="emergency" @if(($activeLevel ?? '') === 'emergency') selected @endif>Emergency</option>
                            <option value="alert" @if(($activeLevel ?? '') === 'alert') selected @endif>Alert</option>
                            <option value="critical" @if(($activeLevel ?? '') === 'critical') selected @endif>Critical</option>
                            <option value="error" @if(($activeLevel ?? '') === 'error') selected @endif>Error</option>
                            <option value="warning" @if(($activeLevel ?? '') === 'warning') selected @endif>Warning</option>
                            <option value="notice" @if(($activeLevel ?? '') === 'notice') selected @endif>Notice</option>
                            <option value="info" @if(($activeLevel ?? '') === 'info') selected @endif>Info</option>
                            <option value="debug" @if(($activeLevel ?? '') === 'debug') selected @endif>Debug</option>
                        </select>
                        <button id="statistyLogExpandAll" class="statisty-btn-secondary">Expand All</button>
                    </div>
                </div>

                <!-- Active Log Info -->
                <div class="statisty-active-log-info">
                    <span class="active-file-label">Active file:</span>
                    <span class="active-file-name">{{ $activeLogFile['name'] ?? 'None' }}</span>
                    @if(!empty($activeLevel))
                        <span class="statisty-badge statisty-badge-filter" style="margin-left:8px;">Filtre: {{ ucfirst($activeLevel) }}</span>
                    @endif
                    @if(!empty($activePageSize))
                        <span id="activePageSizeBadge" class="statisty-badge" style="margin-left:8px;">Affichage: {{ $activePageSize }}</span>
                    @else
                        <span id="activePageSizeBadge" class="statisty-badge" style="margin-left:8px; display:none;"></span>
                    @endif
                    <span id="logEntriesCount" class="active-file-label" style="margin-left:12px;"></span>
                </div>

                <!-- Log entries — fixed height, scrollable -->
                <div class="statisty-log-entries-scroll-wrapper">
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
                </div>

                <!-- Pagination bar -->
                <div class="log-pagination-bar" id="logPaginationBar">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <label style="font-size:12px;font-weight:600;color:var(--text-secondary);">Afficher :</label>
                        <select id="logPageSize" style="padding:4px 8px;border-radius:6px;border:1px solid var(--border-light);background:#fff;color:var(--text-primary);font-size:12px;font-family:var(--font-sans);">
                            <option value="25" @if((int)($activePageSize ?? 25) === 25) selected @endif>25</option>
                            <option value="50" @if((int)($activePageSize ?? 25) === 50) selected @endif>50</option>
                            <option value="100" @if((int)($activePageSize ?? 25) === 100) selected @endif>100</option>
                            <option value="200" @if((int)($activePageSize ?? 25) === 200) selected @endif>200</option>
                        </select>
                        <span id="logPaginationInfo" style="font-size:12px;color:var(--text-secondary);"></span>
                    </div>
                    <div style="display:flex;gap:4px;align-items:center;">
                        <button id="logPrevBtn" class="log-page-btn" disabled>&#8249;</button>
                        <span id="logPageIndicator" style="font-size:12px;font-weight:600;color:var(--text-primary);padding:5px 10px;"></span>
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
            const activeFileName   = @json($activeLogFile['name'] ?? '');
            const activePageSize   = parseInt(@json($activePageSize ?? 25), 10) || 25;
            const entriesContainer = document.getElementById('statistyLogEntries');
            const scrollWrapper    = entriesContainer ? entriesContainer.closest('.statisty-log-entries-scroll-wrapper') : null;
            const pageSizeEl       = document.getElementById('logPageSize');
            const prevBtn          = document.getElementById('logPrevBtn');
            const nextBtn          = document.getElementById('logNextBtn');
            const pageIndicator    = document.getElementById('logPageIndicator');
            const paginationInfo   = document.getElementById('logPaginationInfo');
            const entriesCount     = document.getElementById('logEntriesCount');

            let allEntries    = entriesContainer ? Array.from(entriesContainer.getElementsByClassName('statisty-log-entry')) : [];
            let visibleEntries = [...allEntries];
            let currentPage   = 1;
            let pageSize      = activePageSize;

            // ensure the select reflects the active page size
            if (pageSizeEl) {
                pageSizeEl.value = String(pageSize);
            }

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

                // Scroll back to top of entries when page changes
                if (scrollWrapper) scrollWrapper.scrollTop = 0;

                if (pageIndicator)  pageIndicator.textContent  = 'Page ' + currentPage + ' / ' + pages;
                if (paginationInfo) paginationInfo.textContent  = total > 0 ? (start + 1) + '–' + end + ' sur ' + total + ' entrées' : '0 entrée';
                if (entriesCount)   entriesCount.textContent    = total + ' entrée(s) correspondante(s)';
                        /* ── File list sidebar ─────────────────────────────────────────────── */
                if (nextBtn) nextBtn.disabled = currentPage >= pages;
            }

            if (searchInput) searchInput.addEventListener('input', filterLogs);
            if (levelSelect) levelSelect.addEventListener('change', function () {
                // submit to server to apply server-side level filtering
                var lvl = this.value;
                var params = new URLSearchParams(window.location.search);
                if (lvl === 'all') {
                    params.delete('level');
                } else {
                    params.set('level', lvl);
                }
                if (activeFileName) {
                    params.set('file', activeFileName);
                }
                var newSearch = params.toString();
                window.location.search = newSearch ? ('?' + newSearch) : '';
            });
            if (pageSizeEl)  pageSizeEl.addEventListener('change', function() {
                pageSize = parseInt(this.value, 10) || 25;
                currentPage = 1;
                renderPage();

                // persist page_size in the URL without reloading
                var params = new URLSearchParams(window.location.search);
                if (pageSize === 25) {
                    params.delete('page_size');
                } else {
                    params.set('page_size', String(pageSize));
                }
                if (activeFileName) params.set('file', activeFileName);
                var newUrl = window.location.pathname + (params.toString() ? ('?' + params.toString()) : '');
                history.replaceState(null, '', newUrl);

                // update badge
                var badge = document.getElementById('activePageSizeBadge');
                if (badge) {
                    badge.textContent = 'Affichage: ' + pageSize;
                    badge.style.display = '';
                }
            });
            if (prevBtn)     prevBtn.addEventListener('click', function() { if (currentPage > 1) { currentPage--; renderPage(); } });
            if (nextBtn)     nextBtn.addEventListener('click', function() { currentPage++; renderPage(); });

            // Toggle stack traces
            document.querySelectorAll('.statisty-log-toggle-trace').forEach(function(button) {
                button.addEventListener('click', function (e) {
                    e.stopPropagation(); // prevent triggering parent click selection twice
                    var entry = this.closest('.statisty-log-entry');
                    var body  = entry.querySelector('.statisty-log-entry-body');
                    body.classList.toggle('expanded');
                    this.textContent = body.classList.contains('expanded') ? 'Réduire' : 'Voir la trace';
                });
            });

            // Click entry to select (becomes sky blue)
            document.querySelectorAll('.statisty-log-entry').forEach(function(entry) {
                entry.addEventListener('click', function() {
                    document.querySelectorAll('.statisty-log-entry').forEach(function(e) {
                        e.classList.remove('selected');
                    });
                    this.classList.add('selected');
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
        /* ── Log viewer layout ─────────────────────────────────────────────── */
        .statisty-logviewer-layout {
            display: flex;
            gap: 0;
            height: calc(100vh - 160px);
            min-height: 500px;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
        }

        /* ── File list sidebar ─────────────────────────────────────────────── */
        .statisty-logviewer-sidebar {
            width: 260px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            background: var(--bg-card);
            border-right: 1px solid var(--border-light);
        }

        .statisty-sidebar-title {
            display: block;
            padding: 14px 16px 10px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border-light);
            flex-shrink: 0;
        }
        .lf-count { font-weight: 400; opacity: .7; }

        /* Badge for active filter */
        .statisty-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-primary);
            background: rgba(0,0,0,0.04);
        }
        .statisty-badge-filter {
            background: rgba(59,130,246,0.08);
            color: var(--color-primary);
            border: 1px solid rgba(59,130,246,0.12);
        }

        /* Scrollable file list */
        .statisty-log-files-list {
            overflow-y: auto;
            flex: 1;
            padding: 8px 0;
        }
        .statisty-log-files-list::-webkit-scrollbar { width: 5px; }
        .statisty-log-files-list::-webkit-scrollbar-thumb { background: var(--border-hover); border-radius: 4px; }

        /* ── Main log panel ────────────────────────────────────────────────── */
        .statisty-logviewer-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #ffffff;
        }

        .statisty-logviewer-filters {
            flex-shrink: 0;
            background-color: #fafbfc;
            border-bottom: 1px solid var(--border-light);
        }
        .statisty-active-log-info {
            flex-shrink: 0;
            background-color: var(--bg-primary);
            border-bottom: 1px solid var(--border-light);
        }

        /* Scrollable entries wrapper — takes remaining height */
        .statisty-log-entries-scroll-wrapper {
            flex: 1;
            overflow-y: auto;
            min-height: 0;
            background-color: #ffffff;
        }
        .statisty-log-entries-scroll-wrapper::-webkit-scrollbar { width: 6px; }
        .statisty-log-entries-scroll-wrapper::-webkit-scrollbar-track { background: rgba(0,0,0,0.02); }
        .statisty-log-entries-scroll-wrapper::-webkit-scrollbar-thumb { background: var(--border-hover); border-radius: 4px; }

        /* Pagination bar always stays at bottom — white theme */
        .log-pagination-bar {
            flex-shrink: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 16px;
            background: #f8fafc;
            border-top: 1px solid var(--border-light);
        }
        .log-page-btn {
            background: #ffffff;
            border: 1px solid var(--border-light);
            color: var(--text-primary);
            padding: 5px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 700;
            transition: var(--transition-fast);
            font-family: var(--font-sans);
        }
        .log-page-btn:hover:not(:disabled) {
            background: #f1f5f9;
            border-color: var(--color-primary);
            color: var(--color-primary);
        }
        .log-page-btn:disabled {
            opacity: 0.4;
            cursor: default;
        }
    </style>
@endsection
