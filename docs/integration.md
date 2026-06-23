# Integration Notes

This document explains quick steps to integrate Statisty into a Laravel app.

1. Publish configuration and views/assets:

```bash
php artisan vendor:publish --tag=statisty-config
php artisan vendor:publish --tag=statisty-views
php artisan vendor:publish --tag=statisty-assets
```

2. Configure `config/statisty.php` to add model allow-lists and definitions.

3. To add business definitions programmatically:

```php
\Statisty\Support\BusinessDefinitionRepository::add('kpis', 'total_sales', [
    'model' => App\Models\Order::class,
    'options' => ['field' => 'total'],
]);
```

4. Run `php artisan statisty:doctor` to sanity-check the install.
