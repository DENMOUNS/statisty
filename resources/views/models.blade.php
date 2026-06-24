@extends('statisty::layout')

@section('content')

{{-- ── Flash messages ──────────────────────────────────────────────────── --}}
@if(session('success'))
<div class="sty-flash sty-flash--ok">{!! session('success') !!}</div>
@endif
@if(session('error'))
<div class="sty-flash sty-flash--err">{!! session('error') !!}</div>
@endif

{{-- ── Tabs ─────────────────────────────────────────────────────────────── --}}
<div class="sty-tabs" id="modelTabs">
    <button class="sty-tab active" data-tab="tab-models">Modèles</button>
    <button class="sty-tab" data-tab="tab-migrations">Migrations</button>
</div>

{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- TAB 1 : MODÈLES                                                        --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
<div id="tab-models" class="sty-tab-panel active">

    {{-- ── Liste ──────────────────────────────────────────────────────── --}}
    <section class="sty-card" style="overflow:hidden; margin-bottom:28px;">
        <table class="sty-table">
            <thead>
                <tr>
                    <th>Modèle</th>
                    <th>Table</th>
                    <th class="right">Colonnes</th>
                    <th class="right">Enregistrements</th>
                    <th class="right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($models as $m)
                <tr>
                    <td>
                        <a href="{{ url(trim(config('statisty.routes.web.prefix'),'/').'/workflow/'.str_replace('\\','%5C',$m['class'])) }}"
                           style="color:var(--color-primary);text-decoration:none;font-weight:600;">{{ $m['short'] }}</a>
                        <span class="sty-sub">{{ $m['class'] }}</span>
                    </td>
                    <td><code class="sty-code">{{ $m['table'] }}</code></td>
                    <td class="right">{{ $m['columns_count'] }}</td>
                    <td class="right">{{ number_format($m['count']) }}</td>
                    <td class="right">
                        <button class="sty-btn sty-btn--purple"
                                onclick="openModelEditor({{ json_encode($m) }})">
                            Modifier
                        </button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="sty-empty">Aucun modèle configuré.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    {{-- ── Créer un Modèle ─────────────────────────────────────────────── --}}
    <h2 class="sty-section-title">Créer un Modèle</h2>
    <section class="sty-card" style="margin-bottom:28px;">
        <form method="POST" action="{{ route('statisty.web.models.store') }}" id="create-form">
            @csrf
            <div class="sty-field-group">
                <label class="sty-label">Nom du modèle</label>
                <input type="text" name="model_name" required placeholder="ex: Invoice, ProjectTask"
                       class="sty-input" style="max-width:320px;">
                <p class="sty-hint">Singulier, PascalCase. La migration est créée automatiquement.</p>
            </div>

            <div class="sty-field-group">
                <label class="sty-label">Champs</label>
                <div id="create-rows" class="sty-rows"></div>
                <button type="button" class="sty-btn sty-btn--ghost" onclick="addFieldRow('create-rows','fields')">
                    + Ajouter un champ
                </button>
            </div>

            <button type="submit" class="sty-btn sty-btn--primary"
                    onclick="return confirm('Générer le modèle et la migration ?')">
                Créer le Modèle
            </button>
        </form>
    </section>

    {{-- ── Panel : éditer modèle existant ─────────────────────────────── --}}
    <div id="model-editor" style="display:none; margin-bottom:28px;">
        <h2 class="sty-section-title">Modifier : <span id="me-title" style="color:var(--color-primary);"></span></h2>
        <section class="sty-card">

            {{-- Sous-onglets fillable / casts --}}
            <div class="sty-subtabs" id="subtabs">
                <button class="sty-subtab active" data-sub="sub-fillable" onclick="switchSub('sub-fillable')">$fillable</button>
                <button class="sty-subtab" data-sub="sub-casts" onclick="switchSub('sub-casts')">$casts</button>
            </div>

            <form method="POST" action="{{ route('statisty.web.models.modify') }}" id="model-modify-form">
                @csrf
                <input type="hidden" name="class_name" id="me-class">
                <input type="hidden" name="mode"       id="me-mode" value="fillable">

                {{-- fillable --}}
                <div id="sub-fillable" class="sty-sub-panel active" style="margin-top:20px;">
                    <p class="sty-hint" style="margin-bottom:12px;">
                        Cochez les colonnes à inclure dans <code>$fillable</code>. Les colonnes déjà sélectionnées sont pré-cochées.
                    </p>
                    <div id="me-fillable-cols" class="sty-checkbox-grid"></div>
                </div>

                {{-- casts --}}
                <div id="sub-casts" class="sty-sub-panel" style="margin-top:20px;">
                    <p class="sty-hint" style="margin-bottom:12px;">
                        Choisissez le cast Eloquent pour chaque colonne.
                    </p>
                    <div id="me-casts-cols" class="sty-cast-grid"></div>
                </div>

                <div style="margin-top:24px; display:flex; gap:10px;">
                    <button type="submit" class="sty-btn sty-btn--primary"
                            onclick="return confirm('Modifier le fichier du modèle ?')">
                        Enregistrer
                    </button>
                    <button type="button" class="sty-btn sty-btn--ghost"
                            onclick="document.getElementById('model-editor').style.display='none'">
                        Annuler
                    </button>
                </div>
            </form>
        </section>
    </div>

</div>{{-- /tab-models --}}


{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- TAB 2 : MIGRATIONS                                                     --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
<div id="tab-migrations" class="sty-tab-panel">

    {{-- ── Créer une Migration (nouveau modèle) ─────────────────────────── --}}
    <h2 class="sty-section-title">Ajouter des colonnes à une table existante</h2>
    <p class="sty-hint" style="margin-bottom:20px;">
        Choisissez un modèle, définissez les nouvelles colonnes.
        Une migration d'altération sera générée automatiquement (<code>add_fields_to_&hellip;_table</code>) et injectée avec le code <code>up()</code> et <code>down()</code> corrects.
    </p>

    <section class="sty-card" style="margin-bottom:28px;">
        <form method="POST" action="{{ route('statisty.web.models.alter') }}" id="alter-form">
            @csrf
            <div class="sty-field-group">
                <label class="sty-label">Modèle cible</label>
                <select name="table_name" required class="sty-select" style="max-width:320px;">
                    <option value="">— Choisissez un modèle —</option>
                    @foreach($models as $m)
                        <option value="{{ $m['table'] }}">{{ $m['short'] }} ({{ $m['table'] }})</option>
                    @endforeach
                </select>
            </div>

            <div class="sty-field-group">
                <label class="sty-label">Nouvelles colonnes</label>
                <div id="alter-rows" class="sty-rows"></div>
                <button type="button" class="sty-btn sty-btn--ghost" onclick="addFieldRow('alter-rows','fields')">
                    + Ajouter un champ
                </button>
            </div>

            <button type="submit" class="sty-btn sty-btn--amber"
                    onclick="return confirm('Générer la migration d\'altération ?')">
                Générer la Migration
            </button>
        </form>
    </section>

</div>{{-- /tab-migrations --}}


{{-- ── Styles ──────────────────────────────────────────────────────────── --}}
<style>
/* Flash */
.sty-flash { padding:14px 18px; border-radius:8px; margin-bottom:20px; font-size:14px; font-weight:500; }
.sty-flash--ok  { background:#dcfce7; color:#166534; border:1px solid #86efac; }
.sty-flash--err { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }

/* Tabs */
.sty-tabs { display:flex; gap:4px; margin-bottom:24px; border-bottom:2px solid var(--border-light); }
.sty-tab  { padding:10px 20px; border:none; background:transparent; font-size:14px; font-weight:600;
            color:var(--text-secondary); cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px;
            transition:all .2s; }
.sty-tab.active, .sty-tab:hover { color:var(--color-primary); border-bottom-color:var(--color-primary); }
.sty-tab-panel { display:none; }
.sty-tab-panel.active { display:block; }

/* Subtabs */
.sty-subtabs { display:flex; gap:2px; border-bottom:1px solid var(--border-light); }
.sty-subtab  { padding:8px 16px; border:none; background:transparent; font-size:13px; font-weight:600;
               color:var(--text-secondary); cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-1px; }
.sty-subtab.active { color:#6d28d9; border-bottom-color:#6d28d9; }
.sty-sub-panel { display:none; }
.sty-sub-panel.active { display:block; }

/* Cards */
.sty-card { background:#fff; border-radius:var(--radius-lg); border:1px solid var(--border-light); box-shadow:var(--shadow-sm); overflow:hidden; }
.sty-card form { padding:24px; }
.sty-section-title { font-size:14px; font-weight:700; color:var(--text-primary); margin:0 0 12px;
                     padding-bottom:8px; border-bottom:2px solid var(--color-primary); display:inline-block; }

/* Table */
.sty-table { width:100%; border-collapse:collapse; text-align:left; }
.sty-table th { padding:12px 16px; font-size:11px; color:var(--text-secondary); text-transform:uppercase;
                letter-spacing:.4px; background:#f8fafc; border-bottom:1px solid var(--border-light); }
.sty-table td { padding:13px 16px; border-bottom:1px solid #f1f5f9; font-size:14px; vertical-align:middle; }
.sty-table .right { text-align:right; }
.sty-empty { text-align:center; color:var(--text-secondary); padding:32px !important; }
.sty-sub { display:block; font-size:11px; color:var(--text-secondary); margin-top:2px; }
.sty-code { background:#f1f5f9; padding:3px 7px; border-radius:4px; font-size:12px; color:#475569; font-family:monospace; }

/* Forms */
.sty-field-group { margin-bottom:20px; }
.sty-label { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--text-secondary); margin-bottom:6px; }
.sty-input  { width:100%; padding:10px 14px; border:1px solid var(--border-light); border-radius:var(--radius-md);
              font-size:14px; background:#f8fafc; box-sizing:border-box; font-family:inherit; }
.sty-select { width:100%; padding:10px 14px; border:1px solid var(--border-light); border-radius:var(--radius-md);
              font-size:14px; background:#fff; box-sizing:border-box; cursor:pointer; }
.sty-hint   { font-size:11px; color:var(--text-secondary); margin:4px 0 0; }

/* Buttons */
.sty-btn         { padding:9px 20px; border:none; border-radius:var(--radius-md); font-weight:600; font-size:13px; cursor:pointer; transition:opacity .15s; }
.sty-btn:hover   { opacity:.85; }
.sty-btn--primary{ background:var(--color-primary); color:#fff; }
.sty-btn--purple { background:#ede9fe; color:#5b21b6; }
.sty-btn--amber  { background:#fef3c7; color:#92400e; }
.sty-btn--ghost  { background:#f1f5f9; color:#475569; border:1px solid var(--border-light); }

/* Field rows (schema builder) */
.sty-rows { display:flex; flex-direction:column; gap:8px; }
.sty-row  { display:flex; gap:8px; align-items:flex-start; }
.sty-row-name   { width:200px; padding:9px 12px; border:1px solid var(--border-light); border-radius:6px;
                  font-family:monospace; font-size:13px; background:#f8fafc; }
.sty-row-type   { width:200px; padding:9px 12px; border:1px solid var(--border-light); border-radius:6px;
                  font-size:13px; background:#fff; cursor:pointer; }
.sty-row-enum   { flex:1; padding:9px 12px; border:1px solid #fbbf24; border-radius:6px;
                  font-size:12px; font-family:monospace; background:#fffbeb; display:none; }
.sty-row-del    { padding:9px 13px; background:#fee2e2; color:#ef4444; border:none; border-radius:6px;
                  cursor:pointer; font-weight:700; font-size:15px; line-height:1; }

/* Fillable checkbox grid */
.sty-checkbox-grid { display:flex; flex-wrap:wrap; gap:10px; }
.sty-checkbox-item { display:flex; align-items:center; gap:6px; background:#f8fafc;
                     border:1px solid var(--border-light); border-radius:6px; padding:8px 12px; cursor:pointer; }
.sty-checkbox-item input[type=checkbox] { accent-color:var(--color-primary); width:15px; height:15px; }
.sty-checkbox-item span { font-size:13px; font-family:monospace; color:var(--text-primary); }

/* Casts grid */
.sty-cast-grid { display:flex; flex-direction:column; gap:8px; }
.sty-cast-row  { display:flex; align-items:center; gap:12px; }
.sty-cast-row label { font-family:monospace; font-size:13px; width:180px; color:var(--text-primary); }
.sty-cast-select { padding:7px 10px; border:1px solid var(--border-light); border-radius:6px; font-size:13px; background:#fff; }
</style>

{{-- ── Scripts ─────────────────────────────────────────────────────────── --}}
<script>
/* ---- TABS ---- */
document.querySelectorAll('.sty-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.sty-tab, .sty-tab-panel').forEach(el => el.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).classList.add('active');
    });
});

/* ---- SUB-TABS ---- */
function switchSub(id) {
    document.querySelectorAll('.sty-subtab, .sty-sub-panel').forEach(el => el.classList.remove('active'));
    document.querySelectorAll(`[data-sub="${id}"]`).forEach(el => el.classList.add('active'));
    document.getElementById(id).classList.add('active');
    // sync hidden mode field
    document.getElementById('me-mode').value = id === 'sub-fillable' ? 'fillable' : 'casts';
}

/* ---- SCHEMA BUILDER ---- */
const TYPES = [
    ['string','Texte court — string'],['text','Texte long — text'],['longText','Très long texte — longText'],
    ['integer','Entier — integer'],['bigInteger','Grand entier — bigInteger'],['tinyInteger','Petit entier — tinyInteger'],
    ['unsignedBigInteger','Entier non signé — unsignedBigInteger'],['decimal','Décimal — decimal'],
    ['float','Flottant — float'],['double','Double — double'],['boolean','Booléen — boolean'],
    ['enum','Énumération — enum'],['date','Date — date'],['dateTime','Date & Heure — dateTime'],
    ['timestamp','Timestamp — timestamp'],['time','Heure — time'],['json','JSON — json'],
    ['jsonb','JSONB — jsonb'],['uuid','UUID — uuid'],['ulid','ULID — ulid'],
    ['foreignId','Clé étrangère — foreignId'],['binary','Binaire — binary'],
    ['ipAddress','Adresse IP — ipAddress'],['macAddress','Adresse MAC — macAddress'],
    ['year','Année — year'],['morphs','Morph — morphs'],
];

function buildTypeSelect(prefix, idx) {
    let s = `<select name="${prefix}[${idx}][type]" class="sty-row-type" onchange="toggleEnum(this)">`;
    TYPES.forEach(([v,l]) => s += `<option value="${v}">${l}</option>`);
    s += '</select>';
    return s;
}

function addFieldRow(containerId, prefix) {
    const c   = document.getElementById(containerId);
    const idx = c.children.length;
    const row = document.createElement('div');
    row.className = 'sty-row';
    row.innerHTML = `
        <input type="text" name="${prefix}[${idx}][name]" placeholder="nom_du_champ" required class="sty-row-name">
        ${buildTypeSelect(prefix, idx)}
        <input type="text" name="${prefix}[${idx}][enum_values]" class="sty-row-enum" placeholder="val1, val2, val3">
        <button type="button" class="sty-row-del" onclick="this.closest('.sty-row').remove()">×</button>
    `;
    c.appendChild(row);
}

function toggleEnum(sel) {
    const enumInput = sel.parentElement.querySelector('.sty-row-enum');
    if (enumInput) {
        enumInput.style.display = sel.value === 'enum' ? 'block' : 'none';
        enumInput.required = sel.value === 'enum';
    }
}

/* ---- OPEN MODEL EDITOR ---- */
const CAST_TYPES = ['string','integer','float','decimal:2','boolean','array','collection',
                    'datetime','date','timestamp','encrypted','hashed'];

function openModelEditor(model) {
    document.getElementById('me-title').textContent = model.short;
    document.getElementById('me-class').value       = model.class_name ?? model.class;

    /* ── Fillable checkboxes ── */
    const fillCont = document.getElementById('me-fillable-cols');
    fillCont.innerHTML = '';
    model.columns.forEach(col => {
        if (['id','created_at','updated_at','deleted_at'].includes(col.name)) return;
        const checked = model.fillable.includes(col.name) ? 'checked' : '';
        const label   = document.createElement('label');
        label.className = 'sty-checkbox-item';
        label.innerHTML = `
            <input type="checkbox" name="selected[]" value="${col.name}" ${checked}>
            <span>${col.name}</span>
            <span style="font-size:10px;color:var(--text-secondary);margin-left:2px;">(${col.db_type})</span>
        `;
        fillCont.appendChild(label);
    });

    /* ── Casts rows ── */
    const castCont = document.getElementById('me-casts-cols');
    castCont.innerHTML = '';
    model.columns.forEach(col => {
        if (['id','created_at','updated_at','deleted_at'].includes(col.name)) return;
        const currentCast = model.casts[col.name] ?? col.cast;
        let sel = `<select name="selected[]" class="sty-cast-select" data-col="${col.name}">`;
        CAST_TYPES.forEach(t => {
            sel += `<option value="${col.name}:${t}" ${t === currentCast ? 'selected' : ''}>${t}</option>`;
        });
        sel += '</select>';
        const row = document.createElement('div');
        row.className = 'sty-cast-row';
        row.innerHTML = `<label>${col.name} <span style="font-size:10px;color:var(--text-secondary);">(${col.db_type})</span></label>${sel}`;
        castCont.appendChild(row);
    });

    /* ── Show panel ── */
    const panel = document.getElementById('model-editor');
    panel.style.display = 'block';
    panel.scrollIntoView({ behavior:'smooth', block:'start' });

    /* Reset to fillable sub-tab */
    switchSub('sub-fillable');
    document.querySelectorAll('.sty-subtab').forEach(el => el.classList.remove('active'));
    document.querySelector('[data-sub="sub-fillable"]').classList.add('active');
}

/* ── On submit: only send relevant section's inputs ── */
document.getElementById('model-modify-form').addEventListener('submit', function() {
    const mode = document.getElementById('me-mode').value;
    if (mode === 'fillable') {
        document.querySelectorAll('#sub-casts .sty-cast-select').forEach(el => el.disabled = true);
    } else {
        document.querySelectorAll('#sub-fillable input[type=checkbox]').forEach(el => el.disabled = true);
    }
});

/* ── Init ── */
document.addEventListener('DOMContentLoaded', () => {
    addFieldRow('create-rows','fields');
    addFieldRow('alter-rows','fields');
});
</script>

@endsection
