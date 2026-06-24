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
                    <a href="{{ url(trim((string) config('statisty.routes.web.prefix', 'web/statisty'), '/') . '/health') }}" class="pill pill-link">Voir le diagnostic complet →</a>
                </span>
            </div>
        </div>

        {{-- ── Grille des KPIs globaux ────────────────────────────────────────── --}}
        <section class="statisty-kpi-grid" aria-label="Métriques globales">
            @foreach($kpis as $kpi)
                @php
                    $val = $kpi->value ?? null;
                    $st  = $kpi->status ?? 'pending';
                @endphp
                <article class="statisty-kpi">
                    <div class="statisty-kpi-label">{{ $kpi->name }}</div>
                    <div class="statisty-kpi-value">
                        @if($st === 'ready')
                            {{ is_numeric($val) ? number_format((float) $val) : $val }}
                        @else
                            <span style="font-size:20px; color: var(--text-secondary);">—</span>
                        @endif
                    </div>
                    <div class="statisty-kpi-meta">
                        <span class="statisty-status-badge status-{{ $st }}">{{ $st }}</span>
                    </div>
                </article>
            @endforeach
        </section>

        {{-- ── Heatmap d'activité globale ────────────────────────────────────────── --}}
        @if(isset($heatmapData) && count($heatmapData) > 0)
        <section class="statisty-activity-heatmap" style="margin-top: 30px; margin-bottom: 30px; background: #fff; padding: 24px; border-radius: var(--radius-lg); border: 1px solid var(--border-light); box-shadow: var(--shadow-sm);">
            <div style="margin-bottom: 16px;">
                <h2 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--text-primary);">Activité Globale</h2>
                <p style="margin: 4px 0 0; font-size: 12px; color: var(--text-secondary);">Créations d'enregistrements sur les 12 dernières semaines</p>
            </div>
            <div id="hc-activity-heatmap" style="width:100%; height:180px;"></div>
        </section>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var rawData = @json($heatmapData);
            
            // Transformer pour Highcharts : x = semaine, y = jour, value = nombre
            // rawData format: [weekIndex, dayOfWeekIso_Minus_1, count, dateStr]
            
            var maxValue = 0;
            var hcData = rawData.map(function(item) {
                var val = item[2];
                if (val > maxValue) maxValue = val;
                // Highcharts Heatmap expects y axis reversed by default if we want top to bottom, 
                // but let's invert Y axis in chart options instead.
                return {
                    x: item[0],
                    y: item[1],
                    value: val,
                    date: item[3]
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
                    visible: false, // hide weeks text, keep it simple like github
                },
                yAxis: {
                    categories: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
                    title: null,
                    reversed: true, // Monday at top
                    labels: { style: { fontSize: '10px', color: '#71717a' } },
                    gridLineWidth: 0,
                    lineWidth: 0
                },
                colorAxis: {
                    min: 0,
                    stops: [
                        [0, '#f8fafc'], // very light empty
                        [0.1, '#fecaca'], // very light red
                        [0.4, '#f87171'], // red
                        [0.7, '#dc2626'], // darker red
                        [1, '#991b1b'] // dark red
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
                        return '<b>' + this.point.date + '</b><br/>' +
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
                    data: hcData,
                    dataLabels: {
                        enabled: false
                    }
                }]
            });
        });
        </script>
        @endif

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
                        <a href="{{ $model['metrics_url'] }}" target="_blank" class="action-api-link" title="Metrics API">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                            Metrics API
                        </a>
                        <a href="{{ $model['table_url'] }}" target="_blank" class="action-api-link" title="Table API">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="3" y1="9" x2="21" y2="9"/></svg>
                            Table API
                        </a>
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
    </style>
@endsection
