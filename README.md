# Statisty 📊

> **Statisty** est un package Laravel conçu pour générer instantanément un tableau de bord analytique puissant, beau et dynamique pour votre application. Il inspecte automatiquement vos modèles Eloquent et génère des KPIs, des graphiques Highcharts interactifs et des DataTables avancées.

---

## 🚀 Fonctionnalités Principales

- **Zéro Configuration Requise :** Installez le package et accédez immédiatement à votre tableau de bord. Statisty découvre automatiquement vos modèles et leurs relations.
- **Tableau de Bord Global :** Une vue d'ensemble avec l'état de santé de l'application, une Heatmap d'activité globale (façon GitHub) et vos métriques clés.
- **Workflows par Modèle :** Des pages dédiées pour chaque modèle avec des graphiques d'évolution, de distribution, et une DataTable avec recherche globale et export (PDF/Excel/CSV).
- **Détection Automatique des KPIs :** Calcule les totaux, les sommes et les moyennes en inspectant le typage de vos colonnes en base de données.
- **Documentation API Automatique :** Génère une documentation de vos routes (hors vendor) en parsant vos contrôleurs, requêtes de formulaires (FormRequests), DTOs et annotations PHPDoc (`@bodyParam`).

---

## 📦 Installation

1. Installez le package via Composer :
```bash
composer require denmouns/statisty
```

2. Publiez les assets (CSS/JS) et le fichier de configuration :
```bash
php artisan vendor:publish --tag=statisty-config
php artisan vendor:publish --tag=statisty-assets
```

3. (Optionnel) Lancez la commande de découverte si vous souhaitez cacher le profil de vos modèles pour de meilleures performances :
```bash
php artisan statisty:discover
```

---

## ⚙️ Configuration

Le fichier de configuration sera publié dans `config/statisty.php`.
Vous pouvez y activer ou désactiver l'API interne, configurer le routage, et cibler les modèles spécifiques à afficher.

### URL d'accès
Par défaut, le tableau de bord est accessible sur :
```
http://votre-app.test/web/statisty/dashboard
```

### Sécurité & Middlewares
Dans `config/statisty.php`, vous pouvez protéger l'accès à Statisty en ajoutant des middlewares Laravel standards (ex: `auth`, `can:view-dashboard`) :

```php
'routes' => [
    'web' => [
        'enabled' => true,
        'prefix' => 'web/statisty',
        'middleware' => ['web', 'auth'], // <- Ajoutez 'auth' ici
    ],
    // ...
]
```

---

## 🧑‍💻 Publication sur Packagist (Mise en ligne)

Pour rendre ce package disponible à tous les développeurs via `composer require`, suivez ces étapes :

1. **Vérifier le `composer.json` :** Assurez-vous que le fichier `composer.json` à la racine de votre package est valide et contient un nom unique, ex : `"name": "votre-pseudo/statisty"`.
2. **Initialiser Git & Pousser sur GitHub :**
   ```bash
   git init
   git add .
   git commit -m "Initial release"
   git branch -M main
   git remote add origin https://github.com/votre-pseudo/statisty.git
   git push -u origin main
   ```
3. **Créer une release (Tag) :** Sur GitHub, créez une *Release* ou taguez simplement votre commit : `git tag v1.0.0` puis `git push --tags`.
4. **Soumettre à Packagist :** 
   - Créez un compte sur [Packagist.org](https://packagist.org/)
   - Cliquez sur **Submit** et collez l'URL de votre dépôt GitHub.
   - Et voilà ! N'importe qui pourra faire `composer require votre-pseudo/statisty`.

---

## 💡 Idées d'évolutions futures

- Export de rapports planifiés (PDF/Excel) par email via le Scheduler Laravel.
- Créateur de requêtes personnalisées (Builder SQL visuel en Drag & Drop).
- Détection d'anomalies de données avec alertes (ex: +300% d'inscriptions aujourd'hui).
- Analyse de Cohorte intégrée pour les modèles liés aux utilisateurs/abonnements.

---

*Développé avec ❤️ pour la communauté Laravel.*
