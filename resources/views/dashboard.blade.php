@extends('statisty::layout')

@section('content')
    <header class="statisty-header">
        <div>
            <p class="statisty-eyebrow">{{ $appName ?? config('app.name') }}</p>
            <h1>Statisty Dashboard</h1>
        </div>
        <div class="statisty-version">v{{ $version ?? '1.0.0' }}</div>
    </header>

    @if($emptyMessage)
        <section class="statisty-empty">
            <h2>No models configured</h2>
            <p>{{ $emptyMessage }}</p>
            <code>config/statisty.php</code>
        </section>
    @else
        <section class="statisty-kpi-grid" aria-label="Dashboard metrics">
            @foreach($kpis as $kpi)
                @php
                    $value = $kpi->value ?? null;
                    $status = $kpi->status ?? 'pending';
                @endphp

                <article class="statisty-kpi">
                    <div class="statisty-kpi-label">{{ $kpi->name }}</div>
                    <div class="statisty-kpi-value">
                        @if($status === 'ready')
                            {{ is_numeric($value) ? number_format((float) $value) : $value }}
                        @else
                            -
                        @endif
                    </div>
                    <div class="statisty-kpi-meta">{{ $status }}</div>
                </article>
            @endforeach
        </section>

        <section class="statisty-workflows" aria-label="Configured workflows">
            @foreach($models as $model)
                <article class="statisty-workflow">
                    <div class="statisty-workflow-header">
                        <div>
                            <h2>{{ $model['label'] }}</h2>
                            <p>{{ $model['class'] }}</p>
                        </div>
                        <div class="statisty-workflow-count">{{ is_numeric($model['count']) ? number_format((float) $model['count']) : $model['count'] }}</div>
                    </div>

                    <div class="statisty-actions">
                        <a href="{{ $model['metrics_url'] }}">Count API</a>
                        <a href="{{ $model['table_url'] }}">Table API</a>
                    </div>

                    @if($model['columns'] === [])
                        <p class="statisty-muted">No visible columns found for this model.</p>
                    @else
                        <div class="statisty-table">
                            <table>
                                <thead>
                                    <tr>
                                        @foreach($model['columns'] as $column)
                                            <th>{{ $column }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($model['rows'] as $row)
                                        <tr>
                                            @foreach($model['columns'] as $column)
                                                <td>{{ $row[$column] ?? '' }}</td>
                                            @endforeach
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ max(1, count($model['columns'])) }}">No rows found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
                </article>
            @endforeach
        </section>
    @endif
@endsection
