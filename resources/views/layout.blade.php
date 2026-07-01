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
    <script>
        (function () {
            var primaryBase = 'https://cdn.jsdelivr.net/npm/highcharts@11.2.2';
            var secondaryBase = 'https://cdnjs.cloudflare.com/ajax/libs/highcharts/11.2.2';
            var scripts = [
                primaryBase + '/highcharts.js',
                primaryBase + '/highcharts-more.js',
                primaryBase + '/modules/heatmap.js',
                primaryBase + '/modules/exporting.js',
                primaryBase + '/modules/export-data.js',
                primaryBase + '/modules/accessibility.js',
            ];

            function loadScript(src) {
                return new Promise(function (resolve, reject) {
                    var script = document.createElement('script');
                    script.src = src;
                    script.charset = 'utf-8';
                    script.async = false;
                    script.onload = resolve;
                    script.onerror = function () {
                        reject(new Error('Failed to load ' + src));
                    };
                    document.head.appendChild(script);
                });
            }

            function loadScriptWithFallback(primary) {
                return loadScript(primary).catch(function () {
                    var fallback = primary.replace(primaryBase, secondaryBase);
                    return loadScript(fallback);
                });
            }

            function sequenceLoad(items) {
                return items.reduce(function (promise, src) {
                    return promise.then(function () {
                        return loadScriptWithFallback(src);
                    });
                }, Promise.resolve());
            }

            window.Statisty = window.Statisty || {};
            window.Statisty.highchartsReady = sequenceLoad(scripts)
                .then(function () { return true; })
                .catch(function (error) {
                    console.error('[Statisty] Highcharts failed to load:', error);
                    return false;
                });

            window.Statisty.waitForHighcharts = function (callback) {
                window.Statisty.highchartsReady.then(function (success) {
                    if (success) {
                        callback(null);
                        return;
                    }
                    callback(new Error('Highcharts unavailable'));
                });
            };
        })();
    </script>

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

            @php
                $currentRouteName = request()->route() ? request()->route()->getName() : null;
                $routeActivePage = null;
                if ($currentRouteName !== null) {
                    if (str_ends_with($currentRouteName, '.workflow')) {
                        $routeActivePage = 'workflow';
                    } else {
                        $routeParts = explode('.', $currentRouteName);
                        $routeActivePage = end($routeParts);
                    }
                }

                $activePage = $activePage ?? $routeActivePage;
                $activeWorkflow = $activeWorkflow ?? null;
                if ($activeWorkflow === null && request()->route()) {
                    $routeModel = request()->route('model');
                    if ($routeModel !== null) {
                        $decodedModel = rawurldecode($routeModel);
                        if (! str_starts_with($decodedModel, '\\')) {
                            $decodedModel = '\\' . $decodedModel;
                        }
                        $activeWorkflow = ltrim($decodedModel, '\\');
                    }
                }
            @endphp

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
                                @php
                                    $isActiveWorkflow = false;
                                    $workflowModel = $workflow['class'] ?? null;

                                    if (isset($activeWorkflow) && $activeWorkflow === $workflowModel) {
                                        $isActiveWorkflow = true;
                                    } else {
                                        $currentPath = trim(request()->path(), '/');
                                        $workflowPath = trim(parse_url($workflow['url'], PHP_URL_PATH), '/');
                                        if ($currentPath === $workflowPath) {
                                            $isActiveWorkflow = true;
                                        }
                                    }
                                @endphp
                                <a href="{{ $workflow['url'] }}" class="statisty-nav-link statisty-workflow-link @if($isActiveWorkflow) active @endif" @if($isActiveWorkflow) aria-current="true" @endif>
                                    <span class="statisty-dot" style="background: @if($isActiveWorkflow) var(--color-primary) @else rgba(255,255,255,0.7) @endif"></span>
                                    <span class="statisty-workflow-name">{{ $workflow['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="statisty-nav-section statisty-theme-section">
                    <span class="statisty-nav-section-title">Palette</span>
                    <div class="statisty-theme-swatch-list">
                        <button type="button" class="statisty-theme-swatch active" style="background:#ff2d20" data-theme="red" data-primary="#ff2d20" data-hover="#e0241b" data-secondary="#6366f1" aria-label="Rouge"></button>
                        <button type="button" class="statisty-theme-swatch" style="background:#4f46e5" data-theme="indigo" data-primary="#4f46e5" data-hover="#4338ca" data-secondary="#22c55e" aria-label="Indigo"></button>
                        <button type="button" class="statisty-theme-swatch" style="background:#10b981" data-theme="emerald" data-primary="#10b981" data-hover="#059669" data-secondary="#7c3aed" aria-label="Emerald"></button>
                        <button type="button" class="statisty-theme-swatch" style="background:#f59e0b" data-theme="amber" data-primary="#f59e0b" data-hover="#d97706" data-secondary="#0ea5e9" aria-label="Amber"></button>
                        <button type="button" class="statisty-theme-swatch" style="background:#0f766e" data-theme="teal" data-primary="#0f766e" data-hover="#115e59" data-secondary="#8b5cf6" aria-label="Teal"></button>
                    </div>
                </div>
            </nav>

            <div class="statisty-sidebar-footer" style="padding: 16px 24px; border-top: 1px solid var(--border-light); margin-top: auto;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap: 12px;">
                    <img src="{{ asset('vendor/statisty/mascotte.png') }}" alt="Mascotte" style="height:32px; width:auto; display:block;" />
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
