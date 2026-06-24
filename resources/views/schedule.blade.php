@extends('statisty::layout')

@section('content')
<header class="statisty-content-header">
    <div>
        <h1>Scheduled Tasks</h1>
        <p class="statisty-muted">Vos tâches planifiées (Cron).</p>
    </div>
</header>

<section class="statisty-panel-section" style="background:#fff; border-radius:var(--radius-lg); border:1px solid var(--border-light); box-shadow:var(--shadow-sm); overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="statisty-table" style="width:100%; border-collapse:collapse; text-align:left;">
            <thead>
                <tr style="background:#f8fafc; border-bottom:1px solid var(--border-light);">
                    <th style="padding:12px 16px; font-size:12px; color:var(--text-secondary); text-transform:uppercase;">Commande</th>
                    <th style="padding:12px 16px; font-size:12px; color:var(--text-secondary); text-transform:uppercase;">Expression</th>
                    <th style="padding:12px 16px; font-size:12px; color:var(--text-secondary); text-transform:uppercase;">Prochaine Exécution</th>
                </tr>
            </thead>
            <tbody>
                @forelse($events as $event)
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:16px;">
                        <div style="font-weight:600; color:var(--color-primary);">{{ $event['command'] ?: 'Closure' }}</div>
                        <div style="font-size:12px; color:var(--text-secondary); margin-top:4px;">{{ $event['description'] }}</div>
                    </td>
                    <td style="padding:16px;">
                        <code style="background:#f1f5f9; padding:4px 8px; border-radius:4px; font-size:13px;">{{ $event['expression'] }}</code>
                    </td>
                    <td style="padding:16px; font-variant-numeric:tabular-nums;">{{ $event['next_run'] }}</td>
                </tr>
                @empty
                <tr><td colspan="3" style="padding:24px; text-align:center; color:var(--text-secondary);">Aucune tâche planifiée trouvée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
