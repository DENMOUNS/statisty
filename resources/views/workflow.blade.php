@extends('statisty::layout')

@section('content')
<header class="statisty-content-header">
    <div>
        <div class="statisty-breadcrumb">
            <a href="{{ url(trim((string) config('statisty.routes.web.prefix', 'web/statisty'), '/') . '/dashboard') }}">Dashboard</a>
            <span class="breadcrumb-separator">&rarr;</span>
            <span class="active-crumb">{{ $modelLabel }}</span>
        </div>
        <h1>{{ $modelLabel }} Analysis</h1>
        <p class="statisty-muted statisty-code-subtitle">{{ $modelClass }}</p>
    </div>
</header>

{{-- KPIs --}}
<section class="statisty-kpi-grid">
    @foreach($kpis as $kpi)
    <article class="statisty-kpi">
        <div class="statisty-kpi-label">{{ $kpi['label'] }}</div>
        <div class="statisty-kpi-value">{{ $kpi['value'] }}</div>
        <div class="statisty-kpi-meta"><span class="statisty-status-badge status-ready">{{ $kpi['sub'] }}</span></div>
    </article>
    @endforeach
</section>

{{-- Contrôles période (partagés entre graphiques) --}}
<section class="statisty-panel-section">
    <div class="wf-chart-controls-bar">
        <div class="wf-period-group">
            <span class="wf-ctrl-label">Période :</span>
            <div class="wf-period-btns">
                <button class="wf-prd active" data-p="day">Jour</button>
                <button class="wf-prd" data-p="week">Semaine</button>
                <button class="wf-prd" data-p="month">Mois</button>
                <button class="wf-prd" data-p="year">Année</button>
                <button class="wf-prd" data-p="custom">Personnalisé</button>
            </div>
        </div>
        <div id="wf-custom-range" class="wf-custom-range" style="display:none;">
            <label style="font-size:12px;font-weight:600;color:var(--text-secondary);">Du :</label>
            <input type="date" id="wf-date-from" class="wf-date-input">
            <label style="font-size:12px;font-weight:600;color:var(--text-secondary);">Au :</label>
            <input type="date" id="wf-date-to" class="wf-date-input">
            <button id="wf-apply-dates" class="wf-apply-btn">Appliquer</button>
        </div>
    </div>

    {{-- 3 graphiques --}}
    <div class="wf-charts-grid">
        {{-- 1. Évolutif (line/area/spline) --}}
        <div class="wf-chart-card">
            <div class="wf-chart-head">
                <div>
                    <h3>Évolution</h3>
                    <p>Insertions sur la période</p>
                </div>
                <select class="wf-type-sel" id="sel-line">
                    <option value="areaspline" selected>Area Spline</option>
                    <option value="spline">Spline</option>
                    <option value="line">Ligne</option>
                    <option value="area">Area</option>
                </select>
            </div>
            <div id="hc-line" style="width:100%;height:280px;"></div>
        </div>

        {{-- 2. Barres / Colonnes --}}
        <div class="wf-chart-card">
            <div class="wf-chart-head">
                <div>
                    <h3>Distribution</h3>
                    <p>Volume par période</p>
                </div>
                <select class="wf-type-sel" id="sel-bar">
                    <option value="column" selected>Colonnes</option>
                    <option value="bar">Barres</option>
                </select>
            </div>
            <div id="hc-bar" style="width:100%;height:280px;"></div>
        </div>

        {{-- 3. Camembert --}}
        <div class="wf-chart-card wf-chart-card--wide">
            <div class="wf-chart-head">
                <div>
                    <h3>Répartition</h3>
                    <p>Top catégories</p>
                </div>
            </div>
            <div id="hc-pie" style="width:100%;height:320px;"></div>
        </div>
    </div>
</section>

{{-- DataTable --}}
<section class="statisty-panel-section">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--border-light);">
        <div>
            <h3 style="margin:0 0 2px;font-size:16px;font-weight:700;">Données</h3>
            <span style="font-size:12px;color:var(--text-secondary);">Recherche globale · Filtres par colonne · Export Excel/PDF · Pagination</span>
        </div>
    </div>

    @if($columns === [])
        <p style="text-align:center;padding:32px;color:var(--text-secondary);">Aucune colonne visible pour ce modèle.</p>
    @else
        <div class="statisty-table-wrapper">
            <table id="wf-datatable" class="statisty-table display nowrap" style="width:100%">
                <thead>
                    <tr>@foreach($columns as $col)<th>{{ $col }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    <tr>@foreach($columns as $col)<td>{{ $row[$col] ?? '' }}</td>@endforeach</tr>
                    @empty
                    <tr><td colspan="{{ count($columns) }}" class="statisty-table-empty">Aucune donnée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</section>

{{-- ══ JAVASCRIPT ════════════════════════════════════════════════════════════ --}}
<script>
document.addEventListener('DOMContentLoaded', function () {

    /* ─── Palette & helpers ──────────────────────────────────────────────── */
    var CHART_URL  = '{{ $chartUrl }}';
    var period     = 'day';
    var dateFrom   = '';
    var dateTo     = '';
    var lineType   = 'areaspline';
    var barType    = 'column';
    var charts     = {};

    var PALETTE = ['#ff2d20','#f97316','#f59e0b','#10b981','#6366f1','#8b5cf6','#ec4899'];

    function buildUrl() {
        var url = CHART_URL + '?period=' + period;
        if (dateFrom) url += '&date_from=' + dateFrom;
        if (dateTo)   url += '&date_to='   + dateTo;
        return url;
    }

    var BASE = {
        chart: { style: { fontFamily: 'Outfit, ui-sans-serif, sans-serif' }, backgroundColor: '#ffffff' },
        title: { text: null },
        credits: { enabled: false },
        xAxis: {
            categories: [],
            gridLineColor: '#f1f5f9',
            lineColor: '#e4e4e7',
            tickColor: '#e4e4e7',
            labels: { style: { color: '#71717a', fontSize: '11px' } }
        },
        yAxis: {
            title: { text: null },
            gridLineColor: '#f1f5f9',
            labels: { style: { color: '#71717a', fontSize: '11px' } }
        },
        tooltip: {
            shared: true,
            backgroundColor: '#ffffff',
            borderColor: '#e4e4e7',
            borderRadius: 8,
            shadow: true
        },
        legend: { enabled: false },
        exporting: { enabled: true }
    };

    /* ─── Rendu graphiques ───────────────────────────────────────────────── */
    function renderLine(data) {
        var labels  = data.labels  || [];
        var dataset = (data.datasets && data.datasets[0]) || { data: [], label: 'Records' };
        var color   = PALETTE[0];

        if (charts.line) { charts.line.destroy(); }
        charts.line = Highcharts.chart('hc-line', Highcharts.merge(BASE, {
            chart: { type: lineType },
            xAxis: { categories: labels },
            plotOptions: {
                series: { lineWidth: 2.5, marker: { radius: 3 } },
                areaspline: { fillColor: { linearGradient: {x1:0,y1:0,x2:0,y2:1}, stops: [[0, color+'55'],[1, color+'00']] } },
                area:       { fillColor: { linearGradient: {x1:0,y1:0,x2:0,y2:1}, stops: [[0, color+'44'],[1, color+'00']] } }
            },
            series: [{ name: dataset.label || 'Records', data: dataset.data || [], color: color }]
        }));
    }

    function renderBar(data) {
        var labels  = data.labels  || [];
        var dataset = (data.datasets && data.datasets[0]) || { data: [], label: 'Records' };

        if (charts.bar) { charts.bar.destroy(); }
        charts.bar = Highcharts.chart('hc-bar', Highcharts.merge(BASE, {
            chart: { type: barType },
            xAxis: { categories: labels },
            plotOptions: { column: { borderRadius: 4, borderWidth: 0 }, bar: { borderRadius: 4, borderWidth: 0 } },
            series: [{
                name: dataset.label || 'Records',
                data: (dataset.data || []).map(function(v, i) { return { y: v, color: PALETTE[i % PALETTE.length] }; })
            }]
        }));
    }

    function renderPie(data) {
        var labels  = data.labels  || [];
        var dataset = (data.datasets && data.datasets[0]) || { data: [] };
        var vals    = dataset.data || [];

        var pieData = labels.map(function(lbl, i) {
            return { name: lbl, y: parseFloat(vals[i]) || 0, color: PALETTE[i % PALETTE.length] };
        });

        if (charts.pie) { charts.pie.destroy(); }
        charts.pie = Highcharts.chart('hc-pie', Highcharts.merge(BASE, {
            chart: { type: 'pie' },
            tooltip: { pointFormat: '<b>{point.name}</b>: {point.y} ({point.percentage:.1f}%)' },
            plotOptions: {
                pie: {
                    allowPointSelect: true,
                    cursor: 'pointer',
                    showInLegend: true,
                    dataLabels: { enabled: true, format: '<b>{point.name}</b>: {point.percentage:.1f}%', style: { fontSize: '11px', fontWeight: '600' } }
                }
            },
            legend: { enabled: true, itemStyle: { fontWeight: '600', fontSize: '12px' } },
            series: [{ name: 'Volume', colorByPoint: true, data: pieData }]
        }));
    }

    function showError(id, msg) {
        var el = document.getElementById(id);
        if (el) el.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#a1a1aa;font-size:13px;font-weight:600;">' + (msg || 'Données indisponibles') + '</div>';
    }

    function loadCharts() {
        var url = buildUrl();
        fetch(url)
            .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(function(data) {
                renderLine(data);
                renderBar(data);
                renderPie(data);
            })
            .catch(function(err) {
                showError('hc-line', 'Données indisponibles');
                showError('hc-bar',  'Données indisponibles');
                showError('hc-pie',  'Données indisponibles');
                console.error('[Statisty] Chart fetch error:', err);
            });
    }

    /* ─── Sélecteurs de type ─────────────────────────────────────────────── */
    var selLine = document.getElementById('sel-line');
    var selBar  = document.getElementById('sel-bar');
    if (selLine) selLine.addEventListener('change', function() {
        lineType = this.value;
        fetch(buildUrl()).then(function(r) { return r.json(); }).then(renderLine).catch(function(){});
    });
    if (selBar) selBar.addEventListener('change', function() {
        barType = this.value;
        fetch(buildUrl()).then(function(r) { return r.json(); }).then(renderBar).catch(function(){});
    });

    /* ─── Sélecteurs de période ──────────────────────────────────────────── */
    document.querySelectorAll('.wf-prd').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.wf-prd').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            period = this.getAttribute('data-p');
            var cr = document.getElementById('wf-custom-range');
            if (cr) cr.style.display = (period === 'custom') ? 'flex' : 'none';
            if (period !== 'custom') { dateFrom = ''; dateTo = ''; loadCharts(); }
        });
    });

    var applyBtn = document.getElementById('wf-apply-dates');
    if (applyBtn) applyBtn.addEventListener('click', function() {
        var f = document.getElementById('wf-date-from');
        var t = document.getElementById('wf-date-to');
        dateFrom = f ? f.value : '';
        dateTo   = t ? t.value : '';
        if (dateFrom || dateTo) loadCharts();
    });

    /* ─── DataTable ──────────────────────────────────────────────────────── */
    if (document.getElementById('wf-datatable') && typeof $.fn.DataTable !== 'undefined') {

        // Cloner la ligne d'en-tête pour les filtres par colonne
        $('#wf-datatable thead tr')
            .clone(true)
            .addClass('wf-filter-row')
            .appendTo('#wf-datatable thead');

        var dt = $('#wf-datatable').DataTable({
            orderCellsTop: true,
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100, 200],
            pagingType: 'full_numbers',
            dom: '<"wf-dt-top"Bf>t<"wf-dt-bottom"lip>',
            buttons: [
                { extend: 'copy',  text: '📋 Copier', className: 'wf-dt-btn', exportOptions: { columns: ':visible' } },
                { extend: 'csv',   text: '📄 CSV',   className: 'wf-dt-btn', exportOptions: { columns: ':visible' } },
                { extend: 'excel', text: '📊 Excel', className: 'wf-dt-btn', exportOptions: { columns: ':visible' } },
                { extend: 'pdf',   text: '📕 PDF',   className: 'wf-dt-btn', exportOptions: { columns: ':visible' } },
                { extend: 'print', text: '🖨 Print', className: 'wf-dt-btn', exportOptions: { columns: ':visible' } }
            ],
            language: {
                search: '',
                searchPlaceholder: 'Recherche globale…',
                paginate: { first: '«', last: '»', previous: '‹', next: '›' },
                info: 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
                infoEmpty: 'Aucune entrée',
                lengthMenu: 'Afficher _MENU_ entrées',
                zeroRecords: 'Aucun résultat trouvé',
                emptyTable: 'Aucune donnée disponible'
            },
            initComplete: function() {
                var api = this.api();
                api.columns().every(function(colIdx) {
                    var col  = this;
                    var cell = $('.wf-filter-row th').eq(colIdx);
                    var head = $(col.header()).text().trim();
                    cell.html('<input type="text" class="wf-col-filter" placeholder="' + head + '…" />');
                    $('input', cell).on('keyup change', function() {
                        if (col.search() !== this.value) { col.search(this.value).draw(); }
                    });
                });
            }
        });
    } else if (document.getElementById('wf-datatable')) {
        console.warn('[Statisty] jQuery DataTables not loaded yet');
    }

    /* ─── Init ───────────────────────────────────────────────────────────── */
    loadCharts();
});
</script>

<style>
/* ─── Contrôles période ────────────────────────────────────────────────── */
.wf-chart-controls-bar {
    display: flex; flex-wrap: wrap; align-items: center;
    gap: 16px; margin-bottom: 20px;
    padding: 14px 18px;
    background: #f9fafb; border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
}
.wf-ctrl-label { font-size:11px; font-weight:700; text-transform:uppercase; color:var(--text-secondary); white-space:nowrap; }
.wf-period-group { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.wf-period-btns  { display:flex; gap:4px; flex-wrap:wrap; }
.wf-prd {
    padding:5px 13px; font-size:12px; font-weight:600;
    border:1px solid var(--border-light); border-radius:var(--radius-sm);
    background:#fff; color:var(--text-secondary); cursor:pointer;
    transition:var(--transition-fast); font-family:var(--font-sans);
}
.wf-prd:hover  { border-color:var(--color-primary); color:var(--color-primary); background:#fef2f2; }
.wf-prd.active { background:var(--color-primary); border-color:var(--color-primary); color:#fff; }
.wf-custom-range { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.wf-date-input {
    padding:5px 10px; border:1px solid var(--border-light); border-radius:var(--radius-sm);
    font-family:var(--font-sans); font-size:12px; color:var(--text-primary);
}
.wf-date-input:focus { outline:none; border-color:var(--color-primary); box-shadow:0 0 0 2px rgba(255,45,32,.1); }
.wf-apply-btn {
    padding:5px 14px; font-size:12px; font-weight:600;
    background:var(--color-primary); color:#fff; border:none;
    border-radius:var(--radius-sm); cursor:pointer; transition:var(--transition-fast);
    font-family:var(--font-sans);
}
.wf-apply-btn:hover { background:var(--color-primary-hover); }

/* ─── Grille graphiques ────────────────────────────────────────────────── */
.wf-charts-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.wf-chart-card {
    background:#fff; border:1px solid var(--border-light);
    border-radius:var(--radius-lg); padding:20px; box-shadow:var(--shadow-sm);
}
.wf-chart-card--wide { grid-column: 1 / -1; }
.wf-chart-head {
    display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;
}
.wf-chart-head h3 { margin:0 0 2px; font-size:14px; font-weight:700; color:var(--text-primary); }
.wf-chart-head p  { margin:0; font-size:11px; color:var(--text-secondary); }
.wf-type-sel {
    padding:4px 8px; border:1px solid var(--border-light); border-radius:var(--radius-sm);
    font-size:11px; font-weight:600; font-family:var(--font-sans); cursor:pointer;
    background:#fff; color:var(--text-primary);
}
.wf-type-sel:focus { outline:none; border-color:var(--color-primary); }
@media (max-width: 768px) {
    .wf-charts-grid { grid-template-columns: 1fr; }
    .wf-chart-card--wide { grid-column: auto; }
}

/* ─── DataTable styling ────────────────────────────────────────────────── */
.wf-dt-top    { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px; }
.wf-dt-bottom { display:flex; justify-content:space-between; align-items:center; margin-top:14px; flex-wrap:wrap; gap:10px; padding-top:12px; border-top:1px solid var(--border-light); }
.wf-dt-btn {
    background:#fff !important; border:1px solid var(--border-light) !important;
    color:var(--text-secondary) !important; font-size:12px !important; font-weight:600 !important;
    border-radius:var(--radius-sm) !important; padding:6px 12px !important;
    margin-right:4px !important; cursor:pointer !important;
    transition:var(--transition-fast) !important; box-shadow:none !important;
    font-family:var(--font-sans) !important;
}
.wf-dt-btn:hover { border-color:var(--color-primary) !important; color:var(--color-primary) !important; background:#fef2f2 !important; }
.wf-col-filter {
    width:100%; padding:5px 8px; border:1px solid var(--border-light);
    border-radius:var(--radius-sm); font-size:11px; font-family:var(--font-sans);
    background:#f9fafb; box-sizing:border-box; color:var(--text-primary);
}
.wf-col-filter:focus { outline:none; border-color:var(--color-primary); background:#fff; box-shadow:0 0 0 2px rgba(255,45,32,.1); }
.wf-filter-row th { padding:5px 10px !important; background:#fafafa !important; border-bottom:1px solid var(--border-light) !important; }

/* DataTables overrides */
.dataTables_filter label  { display:flex; align-items:center; gap:6px; font-size:13px; font-weight:600; color:var(--text-secondary); }
.dataTables_filter input  {
    padding:6px 12px; border:1px solid var(--border-light); border-radius:var(--radius-md);
    font-size:13px; font-family:var(--font-sans); background:#f9fafb; color:var(--text-primary);
}
.dataTables_filter input:focus { outline:none; border-color:var(--color-primary); background:#fff; box-shadow:0 0 0 2px rgba(255,45,32,.1); }
.dataTables_length { font-size:12px; color:var(--text-secondary); }
.dataTables_length select {
    padding:4px 8px; border:1px solid var(--border-light); border-radius:var(--radius-sm);
    font-size:12px; font-family:var(--font-sans); margin:0 4px; background:#fff;
}
.dataTables_info { font-size:12px; color:var(--text-secondary); }

.dataTables_paginate { font-size:12px; }
.dataTables_paginate .paginate_button {
    border-radius:var(--radius-sm) !important; border:1px solid var(--border-light) !important;
    background:#fff !important; color:var(--text-secondary) !important;
    font-size:12px !important; font-weight:600 !important;
    padding:5px 11px !important; margin-left:3px !important;
    cursor:pointer !important; transition:var(--transition-fast) !important;
    font-family:var(--font-sans) !important;
}
.dataTables_paginate .paginate_button.current,
.dataTables_paginate .paginate_button.current:hover {
    background:var(--color-primary) !important;
    border-color:var(--color-primary) !important;
    color:#fff !important;
}
.dataTables_paginate .paginate_button:not(.disabled):hover {
    border-color:var(--color-primary) !important;
    color:var(--color-primary) !important;
    background:#fef2f2 !important;
}
.dataTables_paginate .paginate_button.disabled,
.dataTables_paginate .paginate_button.disabled:hover {
    opacity: 0.4 !important; cursor: default !important;
    background:#fff !important; color:var(--text-secondary) !important;
}
</style>
@endsection
