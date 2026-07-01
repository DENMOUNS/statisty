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
    <link rel="stylesheet" href="{{ asset('vendor/statisty/statisty.css') }}?v={{ file_exists(public_path('vendor/statisty/statisty.css')) ? filemtime(public_path('vendor/statisty/statisty.css')) : time() }}">

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
            var primaryBase = 'https://code.highcharts.com/11.2.2';
            var secondaryBase = 'https://cdn.jsdelivr.net/npm/highcharts@11.2.2';
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

    <script src="{{ asset('vendor/statisty/statisty.js') }}?v={{ file_exists(public_path('vendor/statisty/statisty.js')) ? filemtime(public_path('vendor/statisty/statisty.js')) : time() }}"></script>
    <style>
        .statisty-navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 60px;
            padding: 0 24px;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
        }

        .statisty-navbar-container {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .statisty-navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 800;
            color: #111827;
            text-decoration: none;
            white-space: nowrap;
        }

        .statisty-navbar-center {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-width: 0;
            flex: 1;
        }

        .statisty-navbar-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 14px;
            border-radius: 8px;
            color: #4b5563;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
        }

        .statisty-navbar-link:hover,
        .statisty-navbar-link.active {
            color: #ff2d20;
            background: rgba(255, 45, 32, 0.08);
        }

        .statisty-navbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }

        .statisty-navbar-theme-picker {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .statisty-navbar-theme-btn {
            width: 34px;
            height: 34px;
            border: 2px solid transparent;
            border-radius: 50%;
            cursor: pointer;
            transition: transform 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .statisty-navbar-theme-btn[data-theme="red"] { background: #ff5252; }
        .statisty-navbar-theme-btn[data-theme="deep-purple"] { background: #7c4dff; }
        .statisty-navbar-theme-btn[data-theme="teal"] { background: #009688; }
        .statisty-navbar-theme-btn[data-theme="amber"] { background: #ffb300; }
        .statisty-navbar-theme-btn[data-theme="pink"] { background: #e91e63; }

        .statisty-navbar-theme-btn:hover,
        .statisty-navbar-theme-btn.active {
            transform: translateY(-1px);
            border-color: rgba(0, 0, 0, 0.16);
            box-shadow: 0 0 0 4px rgba(0, 0, 0, 0.08);
        }

        .statisty-navbar-menu-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #ffffff;
            color: #111827;
            cursor: pointer;
        }

        @media (max-width: 991px) {
            .statisty-navbar-center {
                display: none;
            }

            .statisty-navbar-theme-picker {
                gap: 6px;
            }

            .statisty-navbar-theme-btn {
                width: 28px;
                height: 28px;
            }

            .statisty-navbar-menu-toggle {
                display: inline-flex;
            }
        }
    </style>
</head>
<body>
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
        if ($activeWorkflow !== null) {
            $activeWorkflow = ltrim(rawurldecode((string) $activeWorkflow), '\\');
        } elseif (request()->route()) {
            $routeModel = request()->route('model');
            if ($routeModel !== null) {
                $activeWorkflow = ltrim(rawurldecode((string) $routeModel), '\\');
            }
        }
    @endphp

    <!-- Global Navbar -->
    <nav class="statisty-navbar">
        <div class="statisty-navbar-container">
            <div class="statisty-navbar-left">
                <a class="statisty-navbar-brand" href="{{ url(trim((string) config('statisty.routes.web.prefix', 'web/statisty'), '/') . '/dashboard') }}">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ff2d20" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M18 9l-5 5-4-4-5 5"/></svg>
                    <span>Statisty</span>
                </a>
            </div>

            <div class="statisty-navbar-center">
                @foreach($statistyNav ?? [] as $nav)
                    <a href="{{ $nav['url'] }}" class="statisty-navbar-link @if(($activePage ?? '') === $nav['key']) active @endif">
                        {{ $nav['label'] }}
                    </a>
                @endforeach
            </div>

            <div class="statisty-navbar-right">
                <div class="statisty-navbar-theme-picker" role="group" aria-label="Choisir une couleur">
                    <button type="button" class="statisty-navbar-theme-btn" data-theme="red" aria-label="Rouge"></button>
                    <button type="button" class="statisty-navbar-theme-btn" data-theme="deep-purple" aria-label="Violet"></button>
                    <button type="button" class="statisty-navbar-theme-btn" data-theme="teal" aria-label="Sarcelle"></button>
                    <button type="button" class="statisty-navbar-theme-btn" data-theme="amber" aria-label="Ambre"></button>
                    <button type="button" class="statisty-navbar-theme-btn" data-theme="pink" aria-label="Rose"></button>
                </div>
                <button id="statistySidebarToggle" aria-label="Toggle Menu" class="statisty-navbar-menu-toggle">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>
    </nav>

    <div class="statisty-layout">
        <!-- Sidebar -->
        <aside class="statisty-sidebar" id="statistySidebar">
            <div class="statisty-sidebar-brand" style="display:flex; align-items:center; gap:10px; padding: 24px 24px 12px 24px;">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#ff2d20" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0px 2px 4px rgba(255,45,32,0.3));"><path d="M3 3v18h18"/><path d="M18 9l-5 5-4-4-5 5"/></svg>
                <span style="font-size:22px; font-weight:800; color:var(--text-primary); letter-spacing:-0.5px;">Statisty</span>
            </div>

            <nav class="statisty-sidebar-nav">
                @if(!empty($sidebarWorkflows))
                    <div class="statisty-nav-section">
                        <span class="statisty-nav-section-title">Workflows</span>
                        <div class="statisty-workflows-links">
                            @foreach($sidebarWorkflows as $workflow)
                                @php
                                    $workflowModel = ltrim((string) ($workflow['class'] ?? ''), '\\');
                                    $isActiveWorkflow = $activeWorkflow !== null && $activeWorkflow === $workflowModel;
                                @endphp
                                <a href="{{ $workflow['url'] }}" class="statisty-nav-link statisty-workflow-link @if($isActiveWorkflow) active @endif" @if($isActiveWorkflow) aria-current="true" @endif>
                                    <span class="statisty-dot" style="background: @if($isActiveWorkflow) var(--color-primary) @else rgba(255,255,255,0.7) @endif"></span>
                                    <span class="statisty-workflow-name">{{ $workflow['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
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
            const themeButtons = document.querySelectorAll('.statisty-navbar-theme-btn');
            const savedTheme = localStorage.getItem('statisty-theme') || 'red';

            if (toggleBtn && sidebar) {
                toggleBtn.addEventListener('click', function () {
                    sidebar.classList.toggle('open');
                });
            }

            function applyTheme(theme) {
                document.body.dataset.theme = theme;
                localStorage.setItem('statisty-theme', theme);

                themeButtons.forEach(function (button) {
                    button.classList.toggle('active', button.dataset.theme === theme);
                });
            }

            themeButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    applyTheme(button.dataset.theme);
                });
            });

            if (savedTheme) {
                applyTheme(savedTheme);
            }
        });
    </script>
</body>
</html>
