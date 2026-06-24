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
@endsection
