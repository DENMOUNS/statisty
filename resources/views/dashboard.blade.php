@extends('statisty::layout')

@section('content')
    @php
        $healthChecks = [];
        try {
            // Quick health snapshot
            $dbOk = true;
            try { \DB::connection()->getPdo(); } catch (\Throwable) { $dbOk = false; }
            $logsWritable = is_writable(storage_path('logs'));
            $modelsCount  = count($models ?? []);
            $isDebug      = (bool) config('app.debug');
        } catch (\Throwable) {
            $dbOk = true; $logsWritable = true; $modelsCount = 0; $isDebug = false;
        }
        $overallStatus = (!$dbOk || !$logsWritable) ? 'failed' : ($isDebug ? 'warning' : 'ready');
        $statusLabel   = $overallStatus === 'failed' ? 'Issues detected' : ($overallStatus === 'warning' ? 'Warning' : 'All systems operational');
    @endphp

    <header class="statisty-content-header">
        <div>
            <h1>Statisty Dashboard</h1>
            <p class="statisty-muted">Vue d'ensemble de votre application. Explorez un workflow pour analyser les données en détail.</p>
        </div>
    </header>

    @if($emptyMessage)
        <section class="statisty-empty">
            <h2>Aucun modèle configuré</h2>
            <p>{{ $emptyMessage }}</p>
            <code>config/statisty.php</code>
        </section>
    @else

        {{-- ── Bannière santé condensée ──────────────────────────────────────── --}}
        <div class="dash-health-banner status-{{ $overallStatus }}">
            <div class="dash-health-icon">
                @if($overallStatus === 'failed')
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                @elseif($overallStatus === 'warning')
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                @else
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                @endif
            </div>
            <div class="dash-health-info">
                <strong>{{ $statusLabel }}</strong>
                <span class="dash-health-pills">
                    <span class="pill pill-{{ $dbOk ? 'ok' : 'fail' }}">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        Database {{ $dbOk ? 'OK' : 'KO' }}
                    </span>
                    <span class="pill pill-{{ $logsWritable ? 'ok' : 'fail' }}">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        Logs {{ $logsWritable ? 'writable' : 'locked' }}
                    </span>
                    <span class="pill pill-{{ $isDebug ? 'warn' : 'ok' }}">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        Debug {{ $isDebug ? 'ON' : 'OFF' }}
                    </span>
                    <span class="pill pill-ok">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        {{ $modelsCount }} workflow{{ $modelsCount > 1 ? 's' : '' }} actif{{ $modelsCount > 1 ? 's' : '' }}
                    </span>
                    <a href="{{ url(trim((string) config('statisty.routes.web.prefix', 'web/statisty'), '/') . '/health') }}" class="pill pill-link">Voir le diagnostic complete →</a>
                </span>
            </div>
        </div>

        <section class="statisty-heatmap-header" style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:12px; align-items:flex-end; margin-top:32px;">
            <div>
                <h2 style="margin:0; font-size:16px; font-weight:700; color:var(--text-primary);">Activité Globale</h2>
                <p style="margin:4px 0 0; font-size:12px; color:var(--text-secondary);">{{ $heatmapCaption }}</p>
            </div>
            <form id="heatmap-year-form" method="GET" style="display:flex; gap:10px; align-items:center;">
                <label for="heatmapYear" style="font-size:12px; color:var(--text-secondary); font-weight:700;">Période :</label>
                <select id="heatmapYear" name="year" onchange="this.form.submit()" style="padding:8px 10px; border:1px solid var(--border-light); border-radius:10px; background:#fff; color:var(--text-primary);">
                    @foreach($heatmapYears as $yearValue => $yearLabel)
                        <option value="{{ $yearValue }}" @if($selectedHeatmapYear === $yearValue) selected @endif>{{ $yearLabel }}</option>
                    @endforeach
                </select>
            </form>
        </section>

        {{-- ── Heatmap d'activité globale ────────────────────────────────────────── --}}
        <section class="statisty-activity-heatmap" style="margin-top: 30px; margin-bottom: 30px; background: #fff; padding: 24px; border-radius: var(--radius-lg); border: 1px solid var(--border-light); box-shadow: var(--shadow-sm);">
            <div id="hc-activity-heatmap" style="width:100%; height:260px;"></div>
            <div id="heatmapEmptyMessage" style="display:none; padding: 28px 14px; text-align:center; color:var(--text-secondary); font-weight:600;">Aucune activité disponible pour cette période. Essayez de sélectionner une autre année ou changez la période.</div>
        </section>

        <div id="dashboardApiModal" class="statisty-modal hidden">
            <div class="statisty-modal-backdrop"></div>
            <div class="statisty-modal-panel">
                <header class="statisty-modal-header">
                    <h2 id="dashboardApiModalTitle" class="statisty-modal-title">API</h2>
                    <button type="button" id="dashboardApiModalClose" class="statisty-modal-close" aria-label="Fermer">×</button>
                </header>
                <div id="dashboardApiModalBody" class="statisty-modal-body"></div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var rawData = @json($heatmapData ?? []);
            var apiModal = document.getElementById('dashboardApiModal');
            var apiModalTitle = document.getElementById('dashboardApiModalTitle');
            var apiModalBody = document.getElementById('dashboardApiModalBody');
            var apiModalClose = document.getElementById('dashboardApiModalClose');
            var heatmapContainer = document.getElementById('hc-activity-heatmap');
            var heatmapEmptyMessage = document.getElementById('heatmapEmptyMessage');

            function renderHeatmap() {
                if (typeof Highcharts === 'undefined' || !Highcharts.seriesTypes || !Highcharts.seriesTypes.heatmap) {
                    heatmapContainer.style.display = 'none';
                    heatmapEmptyMessage.style.display = 'block';
                    return;
                }

                if (!rawData || rawData.length === 0) {
                    heatmapContainer.style.display = 'none';
                    heatmapEmptyMessage.style.display = 'block';
                    return;
                }

                heatmapContainer.style.display = 'block';
                heatmapEmptyMessage.style.display = 'none';

                var heatmapPoints = rawData.map(function(point) {
                    return {
                        x: point[0],
                        y: point[1],
                        value: point[2],
                        date: point[3] || null
                    };
                });

                Highcharts.chart('hc-activity-heatmap', {
                    chart: {
                        type: 'heatmap',
                        marginTop: 10,
                        marginBottom: 20,
                        plotBorderWidth: 0,
                        backgroundColor: 'transparent',
                        style: { fontFamily: 'Outfit, ui-sans-serif, sans-serif' }
                    },
                    title: { text: null },
                    credits: { enabled: false },
                    exporting: { enabled: false },
                    xAxis: {
                        visible: false,
                    },
                    yAxis: {
                        categories: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
                        title: null,
                        reversed: true,
                        labels: { style: { fontSize: '10px', color: '#71717a' } },
                        gridLineWidth: 0,
                        lineWidth: 0
                    },
                    colorAxis: {
                        min: 0,
                        stops: [
                            [0, '#f8fafc'],
                            [0.1, '#fecaca'],
                            [0.4, '#f87171'],
                            [0.7, '#dc2626'],
                            [1, '#991b1b']
                        ]
                    },
                    legend: {
                        align: 'right',
                        layout: 'vertical',
                        margin: 0,
                        verticalAlign: 'top',
                        y: 10,
                        symbolHeight: 120,
                        itemStyle: { fontSize: '10px', color: '#71717a' }
                    },
                    tooltip: {
                        formatter: function () {
                            var dateLabel = this.point.date || this.point.name || 'Date inconnue';
                            return '<b>' + dateLabel + '</b><br/>' +
                                this.point.value + ' enregistrement(s)';
                        },
                        backgroundColor: '#ffffff',
                        borderColor: '#e4e4e7',
                        borderRadius: 8,
                        shadow: true
                    },
                    series: [{
                        name: 'Activité',
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        data: heatmapPoints,
                        dataLabels: {
                            enabled: false
                        }
                    }]
                });
            }

            function tryRenderHeatmap() {
                if (typeof window.Statisty === 'undefined') {
                    heatmapContainer.style.display = 'none';
                    heatmapEmptyMessage.style.display = 'block';
                    return;
                }

                function failHeatmap() {
                    heatmapContainer.style.display = 'none';
                    heatmapEmptyMessage.style.display = 'block';
                }

                if (typeof window.Statisty.waitForHeatmap === 'function') {
                    window.Statisty.waitForHeatmap(function (err) {
                        if (err) {
                            console.error('[Statisty] Heatmap module unavailable:', err);
                            failHeatmap();
                            return;
                        }

                        renderHeatmap();
                    });
                    return;
                }

                if (typeof window.Statisty.waitForHighcharts === 'function') {
                    window.Statisty.waitForHighcharts(function (err) {
                        if (err) {
                            console.error('[Statisty] Highcharts unavailable:', err);
                            failHeatmap();
                            return;
                        }

                        renderHeatmap();
                    });
                    return;
                }

                failHeatmap();
            }

            tryRenderHeatmap();

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function formatApiValue(value) {
                if (value === null || value === undefined) {
                    return '';
                }

                if (typeof value === 'object') {
                    return escapeHtml(JSON.stringify(value, null, 2));
                }

                return escapeHtml(value);
            }

            function showModal() {
                apiModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }

            function hideModal() {
                apiModal.classList.add('hidden');
                document.body.style.overflow = '';
                apiModalBody.innerHTML = '';
                if ($.fn.DataTable && $.fn.DataTable.isDataTable('#dashboardApiTable')) {
                    $('#dashboardApiTable').DataTable().destroy();
                }
            }

            apiModalClose.addEventListener('click', hideModal);
            apiModal.querySelector('.statisty-modal-backdrop').addEventListener('click', hideModal);
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    hideModal();
                }
            });

            function renderMetricsModal(model, url, metricsList) {
                apiModalTitle.textContent = model + ' — Metrics';
                apiModalBody.innerHTML = '<div class="statisty-api-loading">Chargement des métriques...</div>';
                showModal();

                fetch(url, { headers: { 'Accept': 'application/json' } })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Erreur réseau');
                        }
                        return response.json();
                    })
                    .then(function (data) {
                        var html = '<div class="dashboard-modal-section"><strong>Metrics détectées</strong><ul class="dashboard-metrics-list">';

                        metricsList.forEach(function (metric) {
                            html += '<li>' + escapeHtml(metric) + '</li>';
                        });
                        html += '</ul></div>';

                        if (Array.isArray(data) && data.length > 0) {
                            html += '<div class="dashboard-modal-section"><strong>Configuration API</strong><table class="dashboard-api-table"><thead><tr>';
                            Object.keys(data[0]).forEach(function (key) {
                                html += '<th>' + escapeHtml(key) + '</th>';
                            });
                            html += '</tr></thead><tbody>';

                            data.forEach(function (item) {
                                html += '<tr>';
                                Object.values(item).forEach(function (value) {
                                    html += '<td>' + formatApiValue(value) + '</td>';
                                });
                                html += '</tr>';
                            });
                            html += '</tbody></table></div>';
                        } else if (data && typeof data === 'object') {
                            html += '<div class="dashboard-modal-section"><strong>Résultat</strong><table class="dashboard-api-table dashboard-api-object"><tbody>';
                            Object.keys(data).forEach(function (key) {
                                html += '<tr><th>' + escapeHtml(key) + '</th><td>' + formatApiValue(data[key]) + '</td></tr>';
                            });
                            html += '</tbody></table></div>';
                        } else {
                            html += '<p>Aucune métrique paramétrée pour ce modèle.</p>';
                        }

                        apiModalBody.innerHTML = html;
                    })
                    .catch(function () {
                        apiModalBody.innerHTML = '<p class="dashboard-api-error">Impossible de charger les métriques. Vérifiez votre connexion ou la configuration du modèle.</p>';
                    });
            }

            function renderTableModal(model, url) {
                apiModalTitle.textContent = model + ' — Table';
                apiModalBody.innerHTML = '<div class="statisty-api-loading">Chargement des données du tableau...</div>';
                showModal();

                fetch(url + (url.includes('?') ? '&' : '?') + 'per_page=50', { headers: { 'Accept': 'application/json' } })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Erreur réseau');
                        }
                        return response.json();
                    })
                    .then(function (json) {
                        var rows = Array.isArray(json.data) ? json.data : (Array.isArray(json) ? json : []);
                        if (rows.length === 0) {
                            apiModalBody.innerHTML = '<p>Aucun enregistrement trouvé.</p>';
                            return;
                        }

                        var columns = Object.keys(rows[0]).map(function (key) {
                            return { title: key, data: key };
                        });

                        var tableMarkup = '<table id="dashboardApiTable" class="statisty-table display nowrap" style="width:100%"><thead><tr>';
                        columns.forEach(function (col) {
                            tableMarkup += '<th>' + escapeHtml(col.title) + '</th>';
                        });
                        tableMarkup += '</tr></thead><tbody>';

                        rows.forEach(function (row) {
                            tableMarkup += '<tr>';
                            columns.forEach(function (col) {
                                tableMarkup += '<td>' + formatApiValue(row[col.data]) + '</td>';
                            });
                            tableMarkup += '</tr>';
                        });
                        tableMarkup += '</tbody></table>';

                        apiModalBody.innerHTML = '<div class="dashboard-modal-table-wrapper">' + tableMarkup + '</div>';

                        if ($.fn.DataTable) {
                            if ($.fn.DataTable.isDataTable('#dashboardApiTable')) {
                                $('#dashboardApiTable').DataTable().destroy();
                            }
                            $('#dashboardApiTable').DataTable({
                                dom: 'Bfrtip',
                                buttons: ['copy', 'csv', 'excel', 'print'],
                                responsive: true,
                                pageLength: 10,
                                order: [],
                                autoWidth: false,
                            });
                        }
                    })
                    .catch(function () {
                        apiModalBody.innerHTML = '<p class="dashboard-api-error">Impossible de charger le tableau. Vérifiez votre connexion ou la configuration du modèle.</p>';
                    });
            }

            document.querySelectorAll('.dashboard-metrics-button').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    renderMetricsModal(btn.dataset.model, btn.dataset.url, JSON.parse(btn.dataset.metrics || '[]'));
                });
            });

            document.querySelectorAll('.dashboard-table-button').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    renderTableModal(btn.dataset.model, btn.dataset.url);
                });
            });
        });
        </script>

        {{-- ── Grille des Workflows ───────────────────────────────────────────── --}}
        <h2 class="section-title-accent">Workflows configurés</h2>
        <section class="statisty-workflow-grid">
            @foreach($models as $model)
                @php $h = md5($model['class']); @endphp
                <article class="statisty-workflow-card" id="wf-{{ $h }}">
                    <div class="workflow-card-header">
                        <div class="workflow-card-title-group">
                            <span class="workflow-card-status-dot"></span>
                            <h3>{{ $model['label'] }}</h3>
                        </div>
                        <span class="workflow-card-badge">
                            {{ is_numeric($model['count']) ? number_format((float) $model['count']) : $model['count'] }} rows
                        </span>
                    </div>
                    <div class="workflow-card-body">
                        <p class="workflow-card-class">{{ $model['class'] }}</p>
                        @if(!empty($model['metrics_list']) && count($model['metrics_list']) > 1)
                            <div class="workflow-card-metrics-wrapper" style="margin-top: 12px;">
                                <span style="font-size:10px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:6px;">Métriques Détectées :</span>
                                <div style="display:flex; flex-wrap:wrap; gap:5px;">
                                    @foreach($model['metrics_list'] as $m)
                                        <span class="workflow-metric-badge" style="font-size:10px; font-weight:600; padding:2px 8px; border-radius:12px; background:rgba(255, 45, 32, 0.05); color:#ff2d20; border:1px solid rgba(255, 45, 32, 0.15);">
                                            {{ $m }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="workflow-card-actions">
                        <button type="button" class="action-api-link dashboard-metrics-button" data-url="{{ $model['metrics_url'] }}" data-model="{{ $model['label'] }}" data-metrics='@json($model['metrics_list'])' title="Metrics API">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                            Metrics API
                        </button>
                        <button type="button" class="action-api-link dashboard-table-button" data-url="{{ $model['table_url'] }}" data-model="{{ $model['label'] }}" title="Table API">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="3" y1="9" x2="21" y2="9"/></svg>
                            Table API
                        </button>
                    </div>
                    <div class="workflow-card-footer">
                        <a href="{{ url(trim((string) config('statisty.routes.web.prefix', 'web/statisty'), '/') . '/workflow/' . str_replace('\\', '%5C', $model['class'])) }}" class="explore-btn">
                            Explore Workflow &rarr;
                        </a>
                    </div>
                </article>
            @endforeach
        </section>

    @endif

    <style>
        /* ── Health Banner ─────────────────────────────────────────────────── */
        .dash-health-banner {
            display: flex;
            align-items: flex-start;
            gap: 18px;
            padding: 20px 24px;
            border-radius: var(--radius-lg);
            border-left: 5px solid;
            background: #fff;
            box-shadow: var(--shadow-sm);
            margin-bottom: 28px;
        }
        .dash-health-banner.status-ready  { border-left-color: #059669; background: #f0fdf4; }
        .dash-health-banner.status-warning { border-left-color: #d97706; background: #fffbeb; }
        .dash-health-banner.status-failed  { border-left-color: #e11d48; background: #fff1f2; }

        .dash-health-icon { flex-shrink:0; margin-top:2px; }
        .dash-health-banner.status-ready  .dash-health-icon { color:#059669; }
        .dash-health-banner.status-warning .dash-health-icon { color:#d97706; }
        .dash-health-banner.status-failed  .dash-health-icon { color:#e11d48; }

        .dash-health-info { display:flex; flex-direction:column; gap:10px; }
        .dash-health-info strong { font-size:16px; font-weight:700; color:var(--text-primary); }

        .dash-health-pills { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
        .pill {
            display:inline-flex; align-items:center; gap:5px;
            padding:4px 10px; border-radius:20px;
            font-size:11px; font-weight:700; letter-spacing:.3px;
        }
        .pill-ok   { background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; }
        .pill-fail { background:#fee2e2; color:#be123c; border:1px solid #fecaca; }
        .pill-warn { background:#fef9c3; color:#a16207; border:1px solid #fef08a; }
        .pill-link {
            background:transparent; color:var(--color-primary);
            border:1px solid var(--color-primary);
            text-decoration:none; transition:var(--transition-fast);
        }
        .pill-link:hover { background:var(--color-primary); color:#fff; }

        .action-api-link {
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:10px 14px;
            border-radius:999px;
            border:1px solid var(--border-light);
            background:#f8fafc;
            color:var(--text-primary);
            cursor:pointer;
            font-size:13px;
            transition: all 0.15s ease;
        }
        .action-api-link:hover {
            background:#fff;
            border-color:#e5e7eb;
            transform:translateY(-1px);
        }

        .statisty-modal.hidden { display:none; }
        .statisty-modal {
            position:fixed;
            inset:0;
            z-index:1200;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:24px;
        }
        .statisty-modal-backdrop {
            position:absolute;
            inset:0;
            background:rgba(15,23,42,0.55);
            backdrop-filter: blur(4px);
        }
        .statisty-modal-panel {
            position:relative;
            width:min(1100px, 100%);
            max-height:min(90vh, 900px);
            overflow:hidden;
            background:#ffffff;
            border-radius:24px;
            box-shadow:0 32px 80px rgba(15,23,42,0.18);
            z-index:10;
            display:flex;
            flex-direction:column;
        }
        .statisty-modal-header {
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:18px 22px;
            border-bottom:1px solid #e5e7eb;
        }
        .statisty-modal-title {
            margin:0;
            font-size:18px;
            font-weight:700;
            color:var(--text-primary);
        }
        .statisty-modal-close {
            border:none;
            background:transparent;
            color:var(--text-secondary);
            font-size:24px;
            cursor:pointer;
            line-height:1;
            padding:0;
        }
        .statisty-modal-body {
            padding:20px 22px 24px;
            overflow:auto;
        }
        .dashboard-modal-section { margin-bottom:24px; }
        .dashboard-metrics-list { margin:12px 0 0 0; padding-left:18px; color:var(--text-primary); }
        .dashboard-metrics-list li { margin-bottom:8px; }
        .dashboard-api-table { width:100%; border-collapse:collapse; font-size:13px; }
        .dashboard-api-table th,
        .dashboard-api-table td { padding:10px 12px; border:1px solid #e5e7eb; text-align:left; vertical-align:top; }
        .dashboard-api-table th { background:#f8fafc; color:var(--text-secondary); font-weight:700; }
        .dashboard-api-object th { width:210px; }
        .dashboard-api-error { color:#b91c1c; font-weight:700; }
        .dashboard-api-loading { color:var(--text-secondary); font-style:italic; }

    </style>
@endsection
