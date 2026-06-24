@extends('statisty::layout')

@section('content')
<header class="statisty-content-header">
    <div>
        <h1>Artisan Commands</h1>
        <p class="statisty-muted">Exécutez vos commandes système depuis l'interface.</p>
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
    @if(session('output'))
        <div style="background:#1e293b; color:#f8fafc; padding:16px; border-radius:8px; margin-bottom:24px; font-family:monospace; white-space:pre-wrap; overflow-x:auto;">
            {{ session('output') }}
        </div>
    @endif

    <form method="POST" action="{{ route('statisty.web.commands.execute') }}" style="display:flex; gap:12px; align-items:center;">
        @csrf
        <select name="command" style="flex:1; padding:10px 14px; border:1px solid var(--border-light); border-radius:var(--radius-md); font-family:var(--font-sans); background:#f8fafc;">
            <option value="">Sélectionnez une commande...</option>
            @foreach($commands as $cmd)
                <option value="{{ $cmd['name'] }}">{{ $cmd['name'] }} - {{ $cmd['description'] }}</option>
            @endforeach
        </select>
        <button type="submit" style="padding:10px 20px; background:var(--color-primary); color:#fff; border:none; border-radius:var(--radius-md); font-weight:600; cursor:pointer;">
            Exécuter
        </button>
    </form>
</section>
@endsection
