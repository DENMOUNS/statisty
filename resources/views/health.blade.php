@extends('statisty::layout')

@section('content')
    @php
        $failedCount = 0;
        $warningCount = 0;
        foreach ($healthChecks as $check) {
            if (($check['status'] ?? '') === 'failed') {
                $failedCount++;
            } elseif (($check['status'] ?? '') === 'warning') {
                $warningCount++;
            }
        }
    @endphp

    <header class="statisty-content-header">
        <div>
            <h1>Project Health</h1>
            <p class="statisty-muted">System diagnostic and resource availability reports.</p>
        </div>
    </header>

    <!-- Global Health Status Card -->
    <div class="statisty-health-summary status-@if($failedCount > 0)failed @elseif($warningCount > 0)warning @elseready @endif">
        <div class="statisty-health-summary-icon">
            @if($failedCount > 0)
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
            @elseif($warningCount > 0)
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            @else
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            @endif
        </div>
        <div class="statisty-health-summary-text">
            <h2>
                @if($failedCount > 0)
                    System issues detected
                @elseif($warningCount > 0)
                    Some warnings detected
                @else
                    All systems operational
                @endif
            </h2>
            <p>
                @if($failedCount > 0)
                    {{ $failedCount }} component(s) reported failures. Please check details below.
                @elseif($warningCount > 0)
                    {{ $warningCount }} component(s) reported warnings. Optimization might be required.
                @else
                    All integrated components are responding normally.
                @endif
            </p>
        </div>
    </div>

    <!-- Health Grid -->
    <section class="statisty-health-grid" aria-label="Health checks">
        @foreach($healthChecks as $check)
            <div class="statisty-health-card status-{{ $check['status'] ?? 'ready' }}">
                <div class="statisty-health-card-header">
                    <h3>{{ $check['label'] }}</h3>
                    <span class="statisty-status-badge status-{{ $check['status'] ?? 'ready' }}">
                        {{ $check['status'] ?? 'ready' }}
                    </span>
                </div>
                <div class="statisty-health-card-value">
                    {{ $check['value'] }}
                </div>
                @if(!empty($check['detail']))
                    <div class="statisty-health-card-detail">
                        <span class="statisty-detail-label">Detail:</span>
                        <pre><code>{{ $check['detail'] }}</code></pre>
                    </div>
                @endif
            </div>
        @endforeach
    </section>

    <!-- Slow Queries Section -->
    <section class="statisty-slow-queries-section" style="margin-top: 32px;">
        <div class="statisty-card-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <div>
                <h2 style="font-size:18px; font-weight:700; color:var(--text-primary); margin:0;">🐢 Slow Queries Tracker</h2>
                <p class="statisty-muted" style="margin:4px 0 0; font-size:13px;">Requêtes SQL exécutées par l'application dont le temps d'exécution dépasse {{ config('statisty.features.slow_queries.threshold_ms', 100) }} ms.</p>
            </div>
            <span class="statisty-status-badge status-@if(count($slowQueries) > 0)warning @elseready @endif" style="font-size:11px; font-weight:700;">
                {{ count($slowQueries) }} détectée(s)
            </span>
        </div>

        <div class="statisty-jobs-container" style="background:#fff; border:1px solid var(--border-light); border-radius:var(--radius-lg); overflow:hidden;">
            @if(empty($slowQueries))
                <div style="padding: 48px; text-align: center; color: var(--text-secondary); font-size: 14px;">
                    🎉 <strong>Aucune requête lente détectée !</strong> Votre application est rapide.
                </div>
            @else
                <div style="overflow-x: auto;">
                    <table class="statisty-table" style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead>
                            <tr style="background: #fafbfc; border-bottom: 1px solid var(--border-light);">
                                <th style="padding: 14px 18px; font-size: 12px; font-weight: 700; color: var(--text-secondary); width: 100px;">Temps</th>
                                <th style="padding: 14px 18px; font-size: 12px; font-weight: 700; color: var(--text-secondary);">Requête SQL</th>
                                <th style="padding: 14px 18px; font-size: 12px; font-weight: 700; color: var(--text-secondary); width: 180px;">Appelé depuis</th>
                                <th style="padding: 14px 18px; font-size: 12px; font-weight: 700; color: var(--text-secondary); width: 160px;">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($slowQueries as $query)
                                <tr style="border-bottom: 1px solid var(--border-light); transition: background-color 0.15s;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'">
                                    <td style="padding: 16px 18px; vertical-align: top;">
                                        @php
                                            $time = $query['time_ms'] ?? 0;
                                            $badgeClass = $time > 500 ? 'status-failed' : 'status-warning';
                                        @endphp
                                        <span class="statisty-status-badge {{ $badgeClass }}" style="font-family: var(--font-mono, monospace); font-weight: 700; font-size: 11.5px; padding: 4px 8px; border-radius: 6px;">
                                            {{ $time }} ms
                                        </span>
                                    </td>
                                    <td style="padding: 16px 18px; vertical-align: top;">
                                        <div style="font-family: var(--font-mono, monospace); font-size: 12px; color: #1e293b; word-break: break-all; max-height: 120px; overflow-y: auto; white-space: pre-wrap; line-height: 1.5; background: #fafafa; border: 1px solid #f1f5f9; padding: 8px 12px; border-radius: 6px;">{{ $query['sql'] }}</div>
                                        @if(!empty($query['bindings']))
                                            <div style="margin-top: 6px; font-size: 11px; color: var(--text-secondary);">
                                                <strong>Bindings :</strong> <code>{{ json_encode($query['bindings']) }}</code>
                                            </div>
                                        @endif
                                    </td>
                                    <td style="padding: 16px 18px; vertical-align: top; font-size: 12.5px; color: #475569;">
                                        <span style="display:inline-flex; align-items:center; gap:6px; background:#f1f5f9; padding:3px 8px; border-radius:4px; font-size:11.5px; font-weight:600; font-family: var(--font-sans);">
                                            📄 {{ $query['caller'] }}
                                        </span>
                                        <div style="font-size: 10px; color: var(--text-muted); margin-top: 4px;">Connexion : {{ $query['connection'] ?? 'default' }}</div>
                                    </td>
                                    <td style="padding: 16px 18px; vertical-align: top; font-size: 12px; color: var(--text-secondary); white-space: nowrap;">
                                        {{ date('H:i:s d/m/Y', strtotime($query['created_at'])) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection
