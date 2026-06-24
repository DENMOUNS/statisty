@extends('statisty::layout')

@section('content')
<header class="statisty-content-header">
    <div>
        <h1>Exceptions Log</h1>
        <p class="statisty-muted">Extraction des dernières exceptions depuis laravel.log</p>
    </div>
</header>

<section class="statisty-panel-section" style="background:#fff; border-radius:var(--radius-lg); border:1px solid var(--border-light); box-shadow:var(--shadow-sm); overflow:hidden;">
    @if(empty($exceptions))
        <div style="padding:40px; text-align:center;">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" style="margin-bottom:12px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <h3 style="margin:0 0 4px; font-weight:700;">Aucune erreur détectée</h3>
            <p style="color:var(--text-secondary); margin:0;">Votre journal est propre, aucune exception trouvée récemment.</p>
        </div>
    @else
        <div style="padding:16px; background:#fef2f2; border-bottom:1px solid #fecaca; display:flex; align-items:center; gap:10px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span style="font-weight:700; color:#991b1b;">{{ count($exceptions) }} exception(s) récente(s)</span>
        </div>
        <div style="padding:0; overflow-x:auto;">
            @foreach($exceptions as $exc)
                <div style="padding:12px 16px; border-bottom:1px solid var(--border-light); font-family:monospace; font-size:12px; color:#334155; white-space:nowrap;">
                    {{ $exc }}
                </div>
            @endforeach
        </div>
    @endif
</section>
@endsection
