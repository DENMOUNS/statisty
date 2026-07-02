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
            var cdnBases = [
                'https://code.highcharts.com/11.2.2',
                'https://cdn.jsdelivr.net/npm/highcharts@11.2.2',
                'https://cdnjs.cloudflare.com/ajax/libs/highcharts/11.2.2',
                'https://unpkg.com/highcharts@11.2.2',
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

            function loadScriptWithFallback(path) {
                var index = 0;

                function tryNext() {
                    if (index >= cdnBases.length) {
                        return Promise.reject(new Error('Failed to load ' + path + ' from all CDNs.'));
                    }

                    var url = cdnBases[index] + '/' + path;
                    index += 1;

                    return loadScript(url).catch(function (error) {
                        console.warn('[Statisty] CDN fallback failed for', url, error);
                        return tryNext();
                    });
                }

                return tryNext();
            }

            function loadScriptsParallel(paths) {
                return Promise.all(paths.map(loadScriptWithFallback));
            }

            window.Statisty = window.Statisty || {};
            window.Statisty.highchartsReady = loadScriptWithFallback('highcharts.js')
                .then(function () { return true; })
                .catch(function (error) {
                    console.error('[Statisty] Highcharts core failed to load:', error);
                    return false;
                });

            window.Statisty.heatmapReady = window.Statisty.highchartsReady.then(function (success) {
                if (!success) {
                    return false;
                }
                return loadScriptWithFallback('modules/heatmap.js')
                    .then(function () { return true; })
                    .catch(function (error) {
                        console.warn('[Statisty] Highcharts heatmap module failed to load:', error);
                        return false;
                    });
            });

            window.Statisty.optionalModulesReady = window.Statisty.highchartsReady.then(function (success) {
                if (!success) {
                    return false;
                }
                return Promise.allSettled([
                    loadScriptWithFallback('highcharts-more.js'),
                    loadScriptWithFallback('modules/exporting.js'),
                    loadScriptWithFallback('modules/export-data.js'),
                    loadScriptWithFallback('modules/accessibility.js')
                ]).then(function (results) {
                    var failed = results.filter(function (result) { return result.status === 'rejected'; });
                    if (failed.length) {
                        console.warn('[Statisty] Some Highcharts optional scripts failed to load:', failed.map(function (result) { return result.reason; }));
                    }
                    return true;
                });
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

            window.Statisty.waitForHeatmap = function (callback) {
                window.Statisty.heatmapReady.then(function (success) {
                    if (success) {
                        callback(null);
                        return;
                    }
                    callback(new Error('Highcharts heatmap unavailable'));
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
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
            transition: color 0.15s ease, background-color 0.15s ease;
        }

        .statisty-navbar-link:hover,
        .statisty-navbar-link.active {
            color: var(--color-primary);
            background: var(--color-primary-soft, rgba(255, 45, 32, 0.08));
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
            gap: 8px;
        }

        .statisty-navbar-theme-btn {
            width: 18px;
            height: 18px;
            border: 1px solid transparent;
            border-radius: 50%;
            cursor: pointer;
            transition: transform 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .statisty-navbar-theme-btn[data-theme="red"] { background: #ef9a9a; }
        .statisty-navbar-theme-btn[data-theme="deep-purple"] { background: #9575cd; }
        .statisty-navbar-theme-btn[data-theme="teal"] { background: #4db6ac; }
        .statisty-navbar-theme-btn[data-theme="amber"] { background: #ffb74d; }
        .statisty-navbar-theme-btn[data-theme="pink"] { background: #f48fb1; }
        .statisty-navbar-theme-btn[data-theme="blue"] { background: #64b5f6; }
        .statisty-navbar-theme-btn[data-theme="green"] { background: #81c784; }

        .statisty-navbar-theme-btn:hover,
        .statisty-navbar-theme-btn.active {
            transform: translateY(-1px);
            border-color: rgba(0, 0, 0, 0.16);
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.08);
        }

        .statisty-navbar-theme-toggle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 1px solid rgba(148, 163, 184, 0.25);
            background: #ffffff;
            color: #374151;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: transform 0.15s ease, background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }

        .statisty-navbar-theme-toggle:hover,
        .statisty-navbar-theme-toggle.active {
            transform: translateY(-1px);
            border-color: rgba(59, 130, 246, 0.35);
            color: #1d4ed8;
        }

        .statisty-icon-sun {
            display: inline-block;
            opacity: 1;
            transition: opacity var(--transition-fast), transform var(--transition-fast);
        }

        .statisty-icon-moon {
            display: none;
            opacity: 0;
            transition: opacity var(--transition-fast), transform var(--transition-fast);
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
                width: 18px;
                height: 18px;
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
                    <button type="button" class="statisty-navbar-theme-btn" data-theme="blue" aria-label="Bleu"></button>
                    <button type="button" class="statisty-navbar-theme-btn" data-theme="green" aria-label="Vert"></button>
                </div>
                <button id="statistyDarkModeToggle" type="button" class="statisty-navbar-theme-toggle" aria-label="Mode sombre">
                    <span class="statisty-icon-sun">☀</span>
                    <span class="statisty-icon-moon">🌙</span>
                </button>
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
                <img src="{{ asset('vendor/statisty/logo.png') }}" alt="Statisty logo" class="statisty-sidebar-logo" />
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

            <div class="statisty-sidebar-footer">
                <div class="statisty-sidebar-footer-inner">
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
            const darkModeToggle = document.getElementById('statistyDarkModeToggle');
            const savedTheme = localStorage.getItem('statisty-theme') || 'red';
            const savedDarkMode = localStorage.getItem('statisty-dark-mode') === 'true';

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

            function applyDarkMode(enabled) {
                document.body.classList.toggle('dark-mode', enabled);
                localStorage.setItem('statisty-dark-mode', enabled);
                if (darkModeToggle) {
                    darkModeToggle.classList.toggle('active', enabled);
                }
            }

            themeButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    applyTheme(button.dataset.theme);
                });
            });

            if (darkModeToggle) {
                darkModeToggle.addEventListener('click', function () {
                    applyDarkMode(!document.body.classList.contains('dark-mode'));
                });
            }

            if (savedTheme) {
                applyTheme(savedTheme);
            }
            if (savedDarkMode) {
                applyDarkMode(savedDarkMode);
            }
        });
    </script>
</body>
</html>
