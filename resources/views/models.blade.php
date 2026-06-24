@extends('statisty::layout')

@section('content')
<header class="statisty-content-header">
    <div>
        <h1>Models Global View</h1>
        <p class="statisty-muted">Vue globale de tous les modèles interrogés par Statisty.</p>
    </div>
</header>

<section class="statisty-panel-section" style="background:#fff; border-radius:var(--radius-lg); border:1px solid var(--border-light); box-shadow:var(--shadow-sm); overflow:hidden;">
    <table class="statisty-table" style="width:100%; border-collapse:collapse; text-align:left;">
        <thead>
            <tr style="background:#f8fafc; border-bottom:1px solid var(--border-light);">
                <th style="padding:12px 16px; font-size:12px; color:var(--text-secondary); text-transform:uppercase;">Class</th>
                <th style="padding:12px 16px; font-size:12px; color:var(--text-secondary); text-transform:uppercase;">Table DB</th>
                <th style="padding:12px 16px; font-size:12px; color:var(--text-secondary); text-transform:uppercase; text-align:right;">Colonnes BDD</th>
                <th style="padding:12px 16px; font-size:12px; color:var(--text-secondary); text-transform:uppercase; text-align:right;">Total Lignes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($models as $model)
            <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:16px; font-weight:600; color:var(--text-primary);">
                    <a href="{{ url(trim(config('statisty.routes.web.prefix'), '/') . '/workflow/' . str_replace('\\', '%5C', $model['class'])) }}" style="color:var(--color-primary); text-decoration:none;">
                        {{ $model['class'] }}
                    </a>
                </td>
                <td style="padding:16px;">
                    <span style="background:#f1f5f9; padding:4px 8px; border-radius:4px; font-family:monospace; font-size:12px; color:var(--text-secondary);">{{ $model['table'] }}</span>
                </td>
                <td style="padding:16px; text-align:right; font-variant-numeric:tabular-nums; font-weight:600;">{{ $model['columns'] }}</td>
                <td style="padding:16px; text-align:right; font-variant-numeric:tabular-nums; font-weight:700;">{{ number_format($model['count']) }}</td>
            </tr>
            @empty
            <tr><td colspan="4" style="padding:24px; text-align:center; color:var(--text-secondary);">Aucun modèle configuré.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>
@endsection
