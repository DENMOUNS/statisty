@extends('statisty::layout')

@section('content')
<header class="statisty-content-header">
    <div>
        <h1>Cache Manager</h1>
        <p class="statisty-muted">Gérez et videz le cache de votre application.</p>
    </div>
</header>

<section class="statisty-panel-section" style="background:#fff; padding:24px; border-radius:var(--radius-lg); border:1px solid var(--border-light); box-shadow:var(--shadow-sm);">
    @if(session('success'))
        <div style="background:#dcfce7; color:#166534; padding:12px; border-radius:8px; margin-bottom:16px;">
            <strong>Succès :</strong> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div style="background:#fee2e2; color:#991b1b; padding:12px; border-radius:8px; margin-bottom:16px;">
            <strong>Erreur :</strong> {{ session('error') }}
        </div>
    @endif

    <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h3 style="margin:0; font-size:16px; font-weight:700;">Configuration actuelle</h3>
            <p style="margin:4px 0 0; font-size:13px; color:var(--text-secondary);">Store par défaut : <strong>{{ $default }}</strong> (Driver: {{ $driver }})</p>
        </div>
        <form method="POST" action="{{ route('statisty.web.cache.clear') }}">
            @csrf
            <button type="submit" style="padding:10px 20px; background:#ef4444; color:#fff; border:none; border-radius:var(--radius-md); font-weight:600; cursor:pointer;" onclick="return confirm('Voulez-vous vraiment vider tout le cache ?')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:4px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                Vider le Cache
            </button>
        </form>
    </div>
</section>
@endsection
