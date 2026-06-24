<p align="center">
    <h1 align="center">📊 Statisty for Laravel</h1>
</p>

<p align="center">
    <strong>Le tableau de bord analytique ultime, généré automatiquement pour vos modèles Eloquent.</strong>
</p>

---

**Statisty** est un package Laravel conçu pour transformer instantanément votre base de données en un tableau de bord analytique riche, interactif et professionnel. Sans avoir à écrire une seule ligne de code frontend, Statisty inspecte vos modèles et génère des KPIs, des graphiques Highcharts et des tableaux de données interactifs.

## 🚀 Fonctionnalités

- **Zéro Configuration Requise :** Installez le package, et votre tableau de bord est prêt.
- **Workflows Dynamiques :** Une page dédiée par modèle comprenant des graphiques (Area, Spline, Pie, Bar) et des DataTables avec filtres, recherche globale et export (PDF, Excel, CSV, Print).
- **Détection Intelligente des KPIs :** Calcule automatiquement les totaux, sommes et moyennes en inspectant le typage de vos colonnes (ex: `price`, `amount`, `total`).
- **Générateur de Documentation API :** Scanne vos contrôleurs, FormRequests et annotations (`@bodyParam`, `@queryParam`) pour générer automatiquement la documentation de votre API.
- **Suivi des Slow Queries :** Détecte et enregistre les requêtes SQL lentes directement dans le tableau de bord pour optimiser les performances.
- **Diagnostic de Santé (Health) :** Vérification en temps réel de votre base de données, du cache, des logs, et du mode debug.

---

## 📦 Installation

1. Installez le package via Composer :
```bash
composer require denmouns/statisty
```

2. Publiez les assets (CSS, JS, Images) et le fichier de configuration :
```bash
php artisan vendor:publish --tag=statisty-config
php artisan vendor:publish --tag=statisty-assets
```

*(Optionnel)* Pour de meilleures performances en production, lancez la commande de découverte afin de cacher la structure de vos modèles :
```bash
php artisan statisty:discover
```

---

## 🚦 Démarrage Rapide

Par défaut, Statisty est directement accessible à l'URL suivante sur votre application locale :

```text
http://votre-app.test/web/statisty/dashboard
```

Sur cette page, vous retrouverez :
- Une **Heatmap d'activité** de votre application sur les 12 dernières semaines.
- Les **KPIs globaux** de vos modèles.
- Un accès rapide aux **Workflows** de chaque modèle configuré.

---

## ⚙️ Configuration Détaillée

Toute la configuration s'effectue dans le fichier publié `config/statisty.php`. Voici les sections clés pour personnaliser Statisty selon vos besoins.

### 1. Sécurité et Accès (Middlewares)
Par défaut, Statisty est ouvert. **En production, il est crucial de protéger l'accès.**
Ouvrez `config/statisty.php` et ajoutez vos middlewares (comme `auth` ou un middleware personnalisé) dans la clé `routes.web.middleware` :

```php
'routes' => [
    'web' => [
        'enabled' => true,
        'prefix' => 'web/statisty',
        'middleware' => ['web', 'auth', 'can:view-dashboard'], // Sécurisez l'accès ici
    ],
    // Vous pouvez également désactiver l'API interne si vous n'utilisez pas les graphiques dynamiques
    'api' => [
        'enabled' => true,
        'prefix' => 'api/statisty',
        'middleware' => ['auth:sanctum'], // Protection de l'API interne
    ],
],
```

### 2. Configuration des Modèles (Workflows)
Statisty vous permet de choisir précisément quels modèles exposer, et surtout quelles colonnes ou relations afficher dans le tableau de données. 

Rendez-vous dans la clé `'models'` de `config/statisty.php` :

```php
'models' => [
    App\Models\Order::class => [
        'enabled' => true,
        // Les colonnes exactes à afficher dans la DataTable
        'columns' => ['id', 'user_id', 'total_amount', 'status', 'created_at'],
        
        // Définir les relations pour extraire des métriques croisées
        'relations' => [
            'user' => ['columns' => ['id', 'name', 'email']],
            'orderItems' => ['columns' => ['id', 'product_id', 'quantity', 'price']],
        ],
    ],
    
    // Vous pouvez désactiver un modèle sans le supprimer du fichier
    App\Models\Invoice::class => [
        'enabled' => false, 
    ],
],
```

> **Astuce :** Si vous souhaitez que Statisty ignore certains modèles (ex: `PersonalAccessToken`), ajoutez-les dans le tableau `'disabled_models'`.

### 3. Masquage des Données Sensibles
Statisty masque par défaut certaines colonnes (comme `password` ou `remember_token`). Vous pouvez étendre cette liste pour protéger vos données confidentielles :

```php
'security' => [
    'hidden_columns' => [
        'password', 'remember_token', 'api_token', 'secret', 'stripe_key', 'ssn'
    ],
],
```

### 4. Suivi des Slow Queries
Statisty intègre un tracker de requêtes lentes. Il écoute les événements de la base de données et consigne les requêtes dépassant un certain seuil.

```php
'features' => [
    'slow_queries' => [
        'enabled' => env('STATISTY_SLOW_QUERIES_ENABLED', true),
        'threshold_ms' => 100, // Enregistrer toute requête prenant plus de 100 millisecondes
    ],
],
```
Vous pouvez consulter ces requêtes directement depuis le menu **Logs** de Statisty.

---

## 📚 Menu de Navigation

Une fois dans Statisty, utilisez la barre latérale pour naviguer :

- **Dashboard :** L'accueil, avec la heatmap globale et un résumé des workflows.
- **Workflows :** Un menu listant tous vos modèles configurés. Cliquez sur un modèle pour voir ses graphiques (Line, Bar, Pie) et exporter ses données.
- **Health :** Un diagnostic de votre environnement (connexion BDD, permissions de logs, mode debug).
- **Logs :** Visionneuse de logs Laravel intégrée (`laravel.log`) et accès aux **Slow Queries**.
- **Jobs :** Interface pour surveiller l'état de vos queues (si utilisé).
- **API Docs :** Documentation auto-générée de votre API basée sur la réflexion de vos contrôleurs et annotations `@bodyParam` / `@queryParam`.

---

## 🤝 Contribution & Support

Si vous trouvez un bug ou souhaitez proposer une nouvelle fonctionnalité, n'hésitez pas à ouvrir une *Issue* ou soumettre une *Pull Request* sur notre dépôt GitHub.

*Développé avec passion pour la communauté Laravel.*
