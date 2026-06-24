@extends('statisty::layout')

@section('content')
    <header class="statisty-content-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <div>
            <h1>API Documentation</h1>
            <p class="statisty-muted">Génération automatique de la documentation de vos routes API en lisant le code de l'application.</p>
        </div>
        <div>
            <button id="downloadMarkdownBtn" class="statisty-btn-primary" style="display:flex; align-items:center; gap:8px;">
                <span>💾 Télécharger en Markdown</span>
            </button>
        </div>
    </header>

    <div class="statisty-doc-layout">
        <!-- Top bar search filters -->
        <div class="statisty-doc-filters">
            <div class="statisty-search-wrapper" style="flex: 1;">
                <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" id="apiDocSearch" placeholder="Rechercher une route (ex: /api/users, GET, UserController)..." aria-label="Recherche API">
            </div>
            <div class="statisty-filters-group">
                <select id="apiMethodFilter" aria-label="Filtrer par méthode HTTP">
                    <option value="all">Toutes les méthodes</option>
                    <option value="GET">GET</option>
                    <option value="POST">POST</option>
                    <option value="PUT">PUT</option>
                    <option value="PATCH">PATCH</option>
                    <option value="DELETE">DELETE</option>
                </select>
            </div>
        </div>

        @if(empty($apiDocs))
            <section class="statisty-empty" style="margin-top:24px;">
                <h2>Aucune route API détectée</h2>
                <p>Les routes de Statisty et du framework sont ignorées. Créez des routes dans <code>routes/api.php</code> ou <code>routes/web.php</code> pour générer la documentation.</p>
            </section>
        @else
            <!-- API Routes list Accordion -->
            <div class="statisty-doc-entries" id="apiDocContainer" style="margin-top:20px; display:flex; flex-direction:column; gap:16px;">
                @foreach($apiDocs as $index => $route)
                    @php
                        $primaryMethod = count($route['methods']) > 0 ? $route['methods'][0] : 'GET';
                    @endphp
                    <article class="statisty-doc-entry" data-uri="{{ strtolower($route['uri']) }}" data-methods="{{ implode(',', $route['methods']) }}" data-action="{{ strtolower($route['action']) }}" data-desc="{{ strtolower($route['description']) }}">
                        <!-- Header Accordion header -->
                        <div class="statisty-doc-header" onclick="toggleAccordion('doc-body-{{ $index }}', this)">
                            <div class="statisty-doc-title">
                                <div class="methods-badges">
                                    @foreach($route['methods'] as $m)
                                        <span class="method-badge method-{{ strtolower($m) }}">{{ $m }}</span>
                                    @endforeach
                                </div>
                                <span class="route-uri">{{ $route['uri'] }}</span>
                                @if($route['is_deprecated'])
                                    <span class="deprecated-badge">DEPRECATED</span>
                                @endif
                            </div>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <span class="route-desc-short">{{ Str::limit($route['description'], 60) }}</span>
                                <span class="accordion-arrow">▼</span>
                            </div>
                        </div>

                        <!-- Body Accordion body -->
                        <div class="statisty-doc-body" id="doc-body-{{ $index }}" style="display: none;">
                            <div class="statisty-doc-section">
                                <h3>Description</h3>
                                <p class="route-description-full">{{ $route['description'] }}</p>
                            </div>

                            <div class="statisty-doc-grid">
                                <div>
                                    <h3>Informations</h3>
                                    <table class="statisty-table-mini">
                                        <tr>
                                            <th>Nom de la route</th>
                                            <td><code>{{ $route['name'] ?: 'N/A' }}</code></td>
                                        </tr>
                                        <tr>
                                            <th>Action contrôleur</th>
                                            <td><code>{{ $route['action'] }}</code></td>
                                        </tr>
                                        <tr>
                                            <th>Middlewares</th>
                                            <td>
                                                <div class="middleware-tags">
                                                    @forelse($route['middleware'] as $mw)
                                                        <span class="mw-tag">{{ $mw }}</span>
                                                    @empty
                                                        <span class="statisty-muted" style="font-size:11px;">Aucun</span>
                                                    @endforelse
                                                </div>
                                            </td>
                                        </tr>
                                        @if($route['response_type'])
                                            <tr>
                                                <th>Type de retour</th>
                                                <td><span class="response-type-badge">{{ $route['response_type'] }}</span></td>
                                            </tr>
                                        @endif
                                    </table>
                                </div>

                                @if(!empty($route['params']))
                                    <div>
                                        <h3>Paramètres d'URL</h3>
                                        <table class="statisty-table-mini">
                                            <thead>
                                                <tr>
                                                    <th>Paramètre</th>
                                                    <th>Type</th>
                                                    <th>Obligatoire</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($route['params'] as $p)
                                                    <tr>
                                                        <td><code>{{ $p['name'] }}</code></td>
                                                        <td>{{ $p['type'] }}</td>
                                                        <td>
                                                            @if($p['required'])
                                                                <span class="req-badge req-true">Oui</span>
                                                            @else
                                                                <span class="req-badge req-false">Non</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>

                            @if(!empty($route['validation_rules']))
                                <div class="statisty-doc-section" style="margin-top:16px;">
                                    <h3>Structure de Requête (Règles de validation)</h3>
                                    <table class="statisty-table-mini">
                                        <thead>
                                            <tr>
                                                <th>Champ</th>
                                                <th>Règles</th>
                                                <th>Requis</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($route['validation_rules'] as $rule)
                                                <tr>
                                                    <td><strong>{{ $rule['field'] }}</strong></td>
                                                    <td><code>{{ $rule['rules'] }}</code></td>
                                                    <td>
                                                        @if($rule['required'])
                                                            <span class="req-badge req-true">Requis</span>
                                                        @else
                                                            <span class="req-badge req-false">Optionnel</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                            <div class="statisty-doc-section" style="margin-top:16px; border-top: 1px solid var(--border-light); padding-top:14px;">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <h4 style="margin:0; font-size:12px; color:var(--text-secondary);">Exemple d'appel cURL</h4>
                                    <button class="statisty-btn-secondary" style="font-size:10px; padding:4px 8px;" onclick="copyCurl(this, '{{ url($route['uri']) }}', '{{ $primaryMethod }}')">📋 Copier cURL</button>
                                </div>
                                <pre class="curl-code"><code>curl -X {{ $primaryMethod }} "{{ url($route['uri']) }}"</code></pre>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    <script>
        function toggleAccordion(id, headerEl) {
            const body = document.getElementById(id);
            const arrow = headerEl.querySelector('.accordion-arrow');
            if (body.style.display === 'none') {
                body.style.display = 'block';
                arrow.textContent = '▲';
                headerEl.parentElement.classList.add('expanded');
            } else {
                body.style.display = 'none';
                arrow.textContent = '▼';
                headerEl.parentElement.classList.remove('expanded');
            }
        }

        function copyCurl(btn, url, method) {
            const curl = `curl -X ${method} "${url}"`;
            navigator.clipboard.writeText(curl).then(() => {
                const oldText = btn.textContent;
                btn.textContent = '✅ Copié !';
                btn.style.borderColor = '#10b981';
                btn.style.color = '#10b981';
                setTimeout(() => {
                    btn.textContent = oldText;
                    btn.style.borderColor = '';
                    btn.style.color = '';
                }, 2000);
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('apiDocSearch');
            const methodFilter = document.getElementById('apiMethodFilter');
            const entries = document.querySelectorAll('.statisty-doc-entry');

            function filterDocs() {
                const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
                const method = methodFilter ? methodFilter.value : 'all';

                entries.forEach(function(entry) {
                    const uri = entry.getAttribute('data-uri');
                    const methods = entry.getAttribute('data-methods').split(',');
                    const action = entry.getAttribute('data-action');
                    const desc = entry.getAttribute('data-desc');

                    const matchesSearch = query === '' || 
                        uri.includes(query) || 
                        action.includes(query) || 
                        desc.includes(query) ||
                        methods.some(m => m.toLowerCase().includes(query));

                    const matchesMethod = method === 'all' || methods.includes(method);

                    if (matchesSearch && matchesMethod) {
                        entry.style.display = 'block';
                    } else {
                        entry.style.display = 'none';
                    }
                });
            }

            if (searchInput) searchInput.addEventListener('input', filterDocs);
            if (methodFilter) methodFilter.addEventListener('change', filterDocs);

            // Handle Markdown Download
            const downloadBtn = document.getElementById('downloadMarkdownBtn');
            if (downloadBtn) {
                downloadBtn.addEventListener('click', function() {
                    const apiData = @json($apiDocs);
                    let md = `# API Documentation\n\n`;
                    md += `Généré automatiquement par **Statisty** le ${new Date().toLocaleDateString()} à ${new Date().toLocaleTimeString()}.\n\n---\n\n`;

                    apiData.forEach(route => {
                        const methodsStr = route.methods.join(', ');
                        md += `## [${methodsStr}] ${route.uri}\n\n`;
                        if (route.is_deprecated) {
                            md += `⚠️ **DEPRECATED**\n\n`;
                        }
                        md += `*   **Description :** ${route.description}\n`;
                        md += `*   **Action Contrôleur :** \`${route.action}\`\n`;
                        if (route.name) {
                            md += `*   **Nom de la route :** \`${route.name}\`\n`;
                        }
                        md += `*   **Middlewares :** \`${route.middleware.join(', ') || 'aucun'}\`\n`;
                        if (route.response_type) {
                            md += `*   **Type de retour :** \`${route.response_type}\`\n`;
                        }
                        md += `\n`;

                        if (route.params && route.params.length > 0) {
                            md += `### Paramètres d'URL\n\n`;
                            md += `| Paramètre | Type | Obligatoire |\n`;
                            md += `| --- | --- | --- |\n`;
                            route.params.forEach(p => {
                                md += `| \`${p.name}\` | ${p.type} | ${p.required ? 'Oui' : 'Non'} |\n`;
                            });
                            md += `\n`;
                        }

                        if (route.validation_rules && route.validation_rules.length > 0) {
                            md += `### Corps de la Requête (Validation FormRequest)\n\n`;
                            md += `| Champ | Règles | Obligatoire |\n`;
                            md += `| --- | --- | --- |\n`;
                            route.validation_rules.forEach(r => {
                                md += `| **\`${r.field}\`** | \`${r.rules}\` | ${r.required ? 'Requis' : 'Optionnel'} |\n`;
                            });
                            md += `\n`;
                        }

                        md += `### Exemple de commande cURL\n\n`;
                        md += `\`\`\`bash\n`;
                        md += `curl -X ${route.methods[0]} "${window.location.origin}${route.uri}"\n`;
                        md += `\`\`\`\n\n`;
                        md += `---\n\n`;
                    });

                    // Download File
                    const blob = new Blob([md], { type: 'text/markdown;charset=utf-8;' });
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.setAttribute('download', 'api_documentation.md');
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                });
            }
        });
    </script>

    <style>
        .statisty-doc-layout {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .statisty-doc-filters {
            display: flex;
            gap: 16px;
            align-items: center;
            background-color: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            box-shadow: var(--shadow-sm);
        }

        .statisty-doc-entry {
            background-color: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: box-shadow var(--transition-fast), border-color var(--transition-fast);
        }
        .statisty-doc-entry:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--border-hover);
        }
        .statisty-doc-entry.expanded {
            border-color: var(--color-primary);
            box-shadow: var(--shadow-md);
        }

        .statisty-doc-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 24px;
            cursor: pointer;
            user-select: none;
        }

        .statisty-doc-title {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .methods-badges {
            display: flex;
            gap: 4px;
        }

        .method-badge {
            font-size: 10px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 6px;
            color: #fff;
            text-transform: uppercase;
        }
        .method-badge.method-get    { background-color: #10b981; }
        .method-badge.method-post   { background-color: #f59e0b; }
        .method-badge.method-put    { background-color: #6366f1; }
        .method-badge.method-patch  { background-color: #8b5cf6; }
        .method-badge.method-delete { background-color: #ef4444; }

        .route-uri {
            font-family: var(--font-mono, monospace);
            font-size: 15px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .deprecated-badge {
            font-size: 9px;
            background-color: #fef2f2;
            color: #ef4444;
            border: 1px solid #fee2e2;
            border-radius: 4px;
            padding: 1px 6px;
            font-weight: 700;
        }

        .route-desc-short {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .accordion-arrow {
            font-size: 11px;
            color: var(--text-muted);
            transition: transform var(--transition-fast);
        }

        .statisty-doc-body {
            padding: 20px 24px;
            border-top: 1px solid var(--border-light);
            background-color: #fafbfc;
        }

        .statisty-doc-section h3 {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--text-secondary);
            margin: 0 0 10px;
        }

        .route-description-full {
            font-size: 14px;
            line-height: 1.6;
            color: var(--text-primary);
            margin: 0;
        }

        .statisty-doc-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 20px;
        }
        @media (max-width: 768px) {
            .statisty-doc-grid { grid-template-columns: 1fr; }
        }

        .statisty-table-mini {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
        }
        .statisty-table-mini th {
            text-align: left;
            padding: 8px 12px;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border-light);
            width: 35%;
            font-weight: 600;
        }
        .statisty-table-mini td {
            padding: 8px 12px;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-light);
        }
        .statisty-table-mini thead th {
            background-color: #f1f5f9;
            color: var(--text-primary);
            width: auto;
        }

        .middleware-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }
        .mw-tag {
            font-size: 10px;
            background-color: #e2e8f0;
            color: #475569;
            border-radius: 4px;
            padding: 1px 6px;
            font-family: var(--font-mono, monospace);
        }

        .response-type-badge {
            font-size: 10px;
            background-color: #ecfdf5;
            color: #065f46;
            border: 1px solid #d1fae5;
            border-radius: 4px;
            padding: 2px 6px;
            font-family: var(--font-mono, monospace);
            font-weight: 700;
        }

        .req-badge {
            font-size: 10.5px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 4px;
        }
        .req-badge.req-true { background-color: #fee2e2; color: #ef4444; }
        .req-badge.req-false { background-color: #f1f5f9; color: #475569; }

        .curl-code {
            background-color: #0f172a;
            color: #38bdf8;
            padding: 12px 16px;
            border-radius: var(--radius-md);
            overflow-x: auto;
            font-family: var(--font-mono, monospace);
            font-size: 12px;
            margin-top: 8px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
@endsection
