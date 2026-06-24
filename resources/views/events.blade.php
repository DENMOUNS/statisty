@extends('statisty::layout')

@section('content')
<header class="statisty-content-header">
    <div>
        <h1>Events Monitor</h1>
        <p class="statisty-muted">Suivi des événements applicatifs.</p>
    </div>
</header>

<section class="statisty-panel-section" style="background:#fff; padding:24px; text-align:center; border-radius:var(--radius-lg); border:1px solid var(--border-light); box-shadow:var(--shadow-sm);">
    <div style="display:inline-flex; align-items:center; justify-content:center; width:48px; height:48px; background:#f1f5f9; color:var(--text-secondary); border-radius:50%; margin-bottom:16px;">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
    </div>
    <h3 style="margin:0 0 8px; font-weight:700;">Fonctionnalité en cours de développement</h3>
    <p style="color:var(--text-secondary); font-size:14px; max-width:400px; margin:0 auto;">L'écoute en temps réel des événements et listeners via le dispatcher Laravel arrivera dans la prochaine version de Statisty.</p>
</section>
@endsection
