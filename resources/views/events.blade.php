@extends('statisty::layout')

@section('content')
<header class="statisty-content-header">
    <div>
        <h1>Events Monitor</h1>
        <p class="statisty-muted">Suivi des événements applicatifs.</p>
    </div>
</header>

<section class="statisty-panel-section" style="background:#fff; border-radius:var(--radius-lg); border:1px solid var(--border-light); box-shadow:var(--shadow-sm); overflow:hidden;">
    <div style="padding:16px; overflow-x:auto;">
        <table id="eventsTable" class="statisty-table display" style="width:100%; border-collapse:collapse; text-align:left;">
            <thead>
                <tr style="background:#f8fafc; border-bottom:1px solid var(--border-light);">
                    <th style="padding:12px; font-size:12px; color:var(--text-secondary); text-transform:uppercase;">Événement</th>
                    <th style="padding:12px; font-size:12px; color:var(--text-secondary); text-transform:uppercase;">Listeners Associés</th>
                </tr>
            </thead>
            <tbody>
                @foreach($eventsData as $data)
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:12px; font-weight:600; color:var(--color-primary); vertical-align:top;">
                            {{ $data['event'] }}
                        </td>
                        <td style="padding:12px; color:#334155; font-family:monospace; font-size:12px;">
                            <ul style="margin:0; padding-left:16px;">
                                @foreach($data['listeners'] as $listener)
                                    <li>{{ $listener }}</li>
                                @endforeach
                            </ul>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof $.fn.DataTable !== 'undefined' && $('#eventsTable').length > 0) {
        $('#eventsTable').DataTable({
            pageLength: 25,
            lengthMenu: [25, 50, 100],
            language: {
                search: '',
                searchPlaceholder: 'Rechercher un événement…',
                lengthMenu: 'Afficher _MENU_',
                info: '_START_ à _END_ sur _TOTAL_',
                paginate: { first: '«', last: '»', previous: '‹', next: '›' }
            }
        });
    }
});
</script>
@endsection
