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

<h2 class="section-title-accent" style="margin-top:40px;">Créer un Schedule Complexe</h2>
<section class="statisty-panel-section" style="background:#fff; border-radius:var(--radius-lg); border:1px solid var(--border-light); box-shadow:var(--shadow-sm); padding:24px;">
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

    <form method="POST" action="{{ route('statisty.web.schedule.store') }}" style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
        @csrf
        <div>
            <label style="display:block; font-size:12px; font-weight:700; color:var(--text-secondary); margin-bottom:6px; text-transform:uppercase;">Commande Artisan</label>
            <input type="text" name="command" placeholder="ex: model:prune" required style="width:100%; padding:10px 14px; border:1px solid var(--border-light); border-radius:var(--radius-md); font-family:var(--font-sans); background:#f8fafc; box-sizing:border-box;">
        </div>
        <div>
            <label style="display:block; font-size:12px; font-weight:700; color:var(--text-secondary); margin-bottom:6px; text-transform:uppercase;">Fréquence d'exécution</label>
            <select name="frequency" required style="width:100%; padding:10px 14px; border:1px solid var(--border-light); border-radius:var(--radius-md); font-family:var(--font-sans); background:#f8fafc; box-sizing:border-box;">
                <option value="">Sélectionnez la fréquence...</option>
                <option value="everySecond">Chaque seconde</option>
                <option value="everyMinute">Chaque minute</option>
                <option value="everyFiveMinutes">Toutes les 5 minutes</option>
                <option value="hourly">Chaque heure</option>
                <option value="everyTwoHours">Toutes les 2 heures</option>
                <option value="daily">Chaque jour (à minuit)</option>
                <option value="weekly">Chaque semaine</option>
                <option value="monthly">Chaque mois</option>
                <option value="yearly">Chaque année</option>
            </select>
        </div>
        <div style="grid-column: 1 / -1; display:flex; justify-content:flex-end; margin-top:10px;">
            <button type="submit" style="padding:10px 24px; background:var(--color-primary); color:#fff; border:none; border-radius:var(--radius-md); font-weight:600; cursor:pointer;" onclick="return confirm('Attention: cela va modifier directement vos fichiers de code. Continuer ?')">
                Enregistrer le Schedule dans le code
            </button>
        </div>
    </form>
</section>
@endsection
