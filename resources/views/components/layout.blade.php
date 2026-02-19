@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<header class="site-header">
    <div class="page site-header-inner">
        <a href="{{ route('categories.index', absolute: false) }}" class="site-header-brand">{{ config('app.name') }}</a>

        @if (isset($breadcrumbs) && trim((string) $breadcrumbs) !== '')
            <nav class="breadcrumbs" aria-label="Breadcrumb">
                {{ $breadcrumbs }}
            </nav>
        @endif
    </div>
</header>

<main class="page">
    {{ $slot }}
</main>
</body>
</html>
