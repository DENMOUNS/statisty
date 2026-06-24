# Statisty

## Francais

Statisty est un package Laravel d'analytics et d'observabilite pour construire des dashboards a partir de modeles Eloquent.

### Installation

```bash
composer require statisty/statisty
```

Publiez la configuration, les vues et les assets:

```bash
php artisan vendor:publish --tag=statisty-config
php artisan vendor:publish --tag=statisty-views
php artisan vendor:publish --tag=statisty-assets
```

### Configuration

Exposez les modeles que Statisty peut lire dans `config/statisty.php`:

```php
'models' => [
    App\Models\User::class => [
        'enabled' => true,
        'columns' => ['id', 'name', 'email', 'created_at'],
        'relations' => [],
    ],
],
```

Les routes API JSON et dashboard web sont separees et configurables:

```php
'routes' => [
    'api' => [
        'enabled' => true,
        'prefix' => 'api/statisty',
        'middleware' => [],
    ],
    'web' => [
        'enabled' => true,
        'prefix' => 'web/statisty',
        'middleware' => ['web'],
    ],
],
```

Routes par defaut:

```text
GET /api/statisty/health
GET /api/statisty/metrics/{model}
GET /api/statisty/tables/{model}
GET /api/statisty/charts/{model}
GET /api/statisty/workspace/{name}
GET /web/statisty/dashboard
```

### Utilisation

```php
use Statisty\Facades\Statisty;

$dashboard = Statisty::workspace('business')
    ->models([
        App\Models\User::class,
        App\Models\Order::class,
    ])
    ->pagination(500)
    ->build();

return $dashboard->toArray();
```

Verifiez l'installation:

```bash
php artisan statisty:doctor
```

## English

Statisty is a Laravel analytics and observability package for building dashboards from Eloquent models.

### Installation

```bash
composer require statisty/statisty
```

Publish the package configuration, views, and assets:

```bash
php artisan vendor:publish --tag=statisty-config
php artisan vendor:publish --tag=statisty-views
php artisan vendor:publish --tag=statisty-assets
```

### Configuration

Expose the models you want Statisty to read in `config/statisty.php`:

```php
'models' => [
    App\Models\User::class => [
        'enabled' => true,
        'columns' => ['id', 'name', 'email', 'created_at'],
        'relations' => [],
    ],
],
```

JSON API routes and web dashboard routes are separated and configurable:

```php
'routes' => [
    'api' => [
        'enabled' => true,
        'prefix' => 'api/statisty',
        'middleware' => [],
    ],
    'web' => [
        'enabled' => true,
        'prefix' => 'web/statisty',
        'middleware' => ['web'],
    ],
],
```

Default routes:

```text
GET /api/statisty/health
GET /api/statisty/metrics/{model}
GET /api/statisty/tables/{model}
GET /api/statisty/charts/{model}
GET /api/statisty/workspace/{name}
GET /web/statisty/dashboard
```

### Usage

```php
use Statisty\Facades\Statisty;

$dashboard = Statisty::workspace('business')
    ->models([
        App\Models\User::class,
        App\Models\Order::class,
    ])
    ->pagination(500)
    ->build();

return $dashboard->toArray();
```

Verify the installation:

```bash
php artisan statisty:doctor
```

## Espanol

Statisty es un paquete de analytics y observabilidad para Laravel que permite construir dashboards a partir de modelos Eloquent.

### Instalacion

```bash
composer require statisty/statisty
```

Publique la configuracion, las vistas y los assets del paquete:

```bash
php artisan vendor:publish --tag=statisty-config
php artisan vendor:publish --tag=statisty-views
php artisan vendor:publish --tag=statisty-assets
```

### Configuracion

Exponga los modelos que Statisty puede leer en `config/statisty.php`:

```php
'models' => [
    App\Models\User::class => [
        'enabled' => true,
        'columns' => ['id', 'name', 'email', 'created_at'],
        'relations' => [],
    ],
],
```

Las rutas de API JSON y del dashboard web estan separadas y son configurables:

```php
'routes' => [
    'api' => [
        'enabled' => true,
        'prefix' => 'api/statisty',
        'middleware' => [],
    ],
    'web' => [
        'enabled' => true,
        'prefix' => 'web/statisty',
        'middleware' => ['web'],
    ],
],
```

Rutas por defecto:

```text
GET /api/statisty/health
GET /api/statisty/metrics/{model}
GET /api/statisty/tables/{model}
GET /api/statisty/charts/{model}
GET /api/statisty/workspace/{name}
GET /web/statisty/dashboard
```

### Uso

```php
use Statisty\Facades\Statisty;

$dashboard = Statisty::workspace('business')
    ->models([
        App\Models\User::class,
        App\Models\Order::class,
    ])
    ->pagination(500)
    ->build();

return $dashboard->toArray();
```

Verifique la instalacion:

```bash
php artisan statisty:doctor
```

## License

This package is proprietary. See [LICENSE.md](LICENSE.md).
