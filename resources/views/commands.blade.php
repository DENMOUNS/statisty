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

    <div style="padding:16px; overflow-x:auto;">
        <table id="commandsTable" class="statisty-table display" style="width:100%; border-collapse:collapse; text-align:left;">
            <thead>
                <tr style="background:#f8fafc; border-bottom:1px solid var(--border-light);">
                    <th style="padding:12px; font-size:12px; color:var(--text-secondary); text-transform:uppercase;">Nom de la commande</th>
                    <th style="padding:12px; font-size:12px; color:var(--text-secondary); text-transform:uppercase;">Description</th>
                    <th style="padding:12px; font-size:12px; color:var(--text-secondary); text-transform:uppercase; text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($commands as $cmd)
                    <tr class="cmd-row" style="border-bottom:1px solid #f1f5f9; position:relative;">
                        <td style="padding:12px; font-weight:600; color:var(--color-primary); width:30%;">{{ $cmd['name'] }}</td>
                        <td style="padding:12px; color:var(--text-secondary);">{{ $cmd['description'] }}</td>
                        <td style="padding:12px; text-align:right;">
                            <form method="POST" action="{{ route('statisty.web.commands.execute') }}" class="cmd-execute-form" style="display:inline-block; opacity:0; transition:opacity 0.2s;">
                                @csrf
                                <input type="hidden" name="command" value="{{ $cmd['name'] }}">
                                <button type="submit" style="padding:6px 12px; background:#10b981; color:#fff; border:none; border-radius:4px; font-weight:600; font-size:11px; cursor:pointer;" onclick="return confirm('Exécuter la commande {{ $cmd['name'] }} ?')">
                                    Exécuter
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<style>
.cmd-row:hover .cmd-execute-form { opacity: 1 !important; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof $.fn.DataTable !== 'undefined' && $('#commandsTable').length > 0) {
        $('#commandsTable').DataTable({
            pageLength: 25,
            lengthMenu: [25, 50, 100],
            language: {
                search: '',
                searchPlaceholder: 'Rechercher une commande…',
                lengthMenu: 'Afficher _MENU_',
                info: '_START_ à _END_ sur _TOTAL_',
                paginate: { first: '«', last: '»', previous: '‹', next: '›' }
            }
        });
    }
});
</script>
@endsection
