<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    <meta name="description" content="{{ $description ?? 'A Laravel sample application: hybrid semantic search over an Opensolr index, through Laravel Scout and the Opensolr API, with a Vue front end.' }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="site-header">
        <div class="wrap">
            <a class="brand" href="{{ route('home') }}">Laravel Opensolr Search</a>
            <nav>
                <a href="{{ route('home') }}" @class(['active' => request()->routeIs('home')])>Index search</a>
                <a href="{{ route('eloquent') }}" @class(['active' => request()->routeIs('eloquent')])>Eloquent search</a>
                <a href="https://opensolr.com/laravel-search" rel="noopener">Driver</a>
                <a href="https://github.com/phpcip/laravel-opensolr-search" rel="noopener">Source</a>
            </nav>
        </div>
    </header>

    <main class="wrap page">
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="wrap">
            Index <code>{{ config('scout-opensolr.index') }}</code> on <a href="https://opensolr.com" rel="noopener">Opensolr</a>,
            searched through <a href="https://packagist.org/packages/opensolr/laravel-scout-opensolr" rel="noopener">opensolr/laravel-scout-opensolr</a>.
        </div>
    </footer>
</body>
</html>
