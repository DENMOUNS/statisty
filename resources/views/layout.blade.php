<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Statisty Dashboard</title>
    <link rel="icon" type="image/png" href="{{ asset('vendor/statisty/mascotte.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/statisty/statisty.css') }}">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">

    <!-- jQuery (must be first) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

    <!-- Highcharts + modules -->
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/highcharts-more.js"></script>
    <script src="https://code.highcharts.com/modules/heatmap.js"></script>
    <script src="https://code.highcharts.com/modules/exporting.js"></script>
    <script src="https://code.highcharts.com/modules/export-data.js"></script>
    <script src="https://code.highcharts.com/modules/accessibility.js"></script>

    <script src="{{ asset('vendor/statisty/statisty.js') }}"></script>
</head>
<body>
    <div class="statisty-layout">
        <!-- Mobile Header -->
        <header class="statisty-mobile-header">
            <div class="statisty-mobile-brand" style="display:flex; align-items:center; gap:8px;">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#ff2d20" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M18 9l-5 5-4-4-5 5"/></svg>
                <span style="font-size:18px; font-weight:800; color:var(--text-primary); letter-spacing:-0.5px;">Statisty</span>
            </div>
            <button id="statistySidebarToggle" aria-label="Toggle Menu" class="statisty-btn-toggle">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
        </header>

        <!-- Sidebar -->
        <aside class="statisty-sidebar" id="statistySidebar">
            <div class="statisty-sidebar-brand" style="display:flex; align-items:center; gap:10px; padding: 24px 24px 12px 24px;">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#ff2d20" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0px 2px 4px rgba(255,45,32,0.3));"><path d="M3 3v18h18"/><path d="M18 9l-5 5-4-4-5 5"/></svg>
                <span style="font-size:22px; font-weight:800; color:var(--text-primary); letter-spacing:-0.5px;">Statisty</span>
            </div>

            <nav class="statisty-sidebar-nav">
                <div class="statisty-nav-section">
                    <span class="statisty-nav-section-title">Navigation</span>
                    @foreach($statistyNav ?? [] as $nav)
                        <a href="{{ $nav['url'] }}" class="statisty-nav-link @if(($activePage ?? '') === $nav['key']) active @endif">
                            <span class="statisty-nav-icon-placeholder statisty-icon-{{ $nav['key'] }}"></span>
                            {{ $nav['label'] }}
                        </a>
                    @endforeach
                </div>

                @if(!empty($sidebarWorkflows))
                    <div class="statisty-nav-section">
                        <span class="statisty-nav-section-title">Workflows</span>
                        <div class="statisty-workflows-links">
                            @foreach($sidebarWorkflows as $workflow)
                                <a href="{{ $workflow['url'] }}" class="statisty-nav-link statisty-workflow-link">
                                    <span class="statisty-dot"></span>
                                    <span class="statisty-workflow-name">{{ $workflow['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </nav>

            <div class="statisty-sidebar-footer" style="padding: 16px 24px; border-top: 1px solid var(--border-light); margin-top: auto;">
                <div style="display:flex; align-items:center; justify-content:space-between;">
                    <span style="font-size: 12px; font-weight: 600; color: var(--text-secondary);">Powered by Statisty</span>
                    <span class="statisty-version-badge">v{{ $version ?? '1.0.0' }}</span>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="statisty-main">
            <div class="statisty-content">
                @yield('content')
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleBtn = document.getElementById('statistySidebarToggle');
            const sidebar   = document.getElementById('statistySidebar');
            if (toggleBtn && sidebar) {
                toggleBtn.addEventListener('click', function () { sidebar.classList.toggle('open'); });
            }
        });
    </script>
</body>
</html>
