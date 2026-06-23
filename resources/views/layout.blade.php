<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Statisty Dashboard</title>
    <link rel="stylesheet" href="{{ asset('vendor/statisty/statisty.css') }}">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <script src="{{ asset('vendor/statisty/statisty.js') }}" defer></script>
</head>
<body>
    <div class="statisty-container">
        @yield('content')
    </div>
</body>
</html>
