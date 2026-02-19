@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="dns-prefetch" href="//cdn.dummyjson.com">
    <link rel="preconnect" href="https://cdn.dummyjson.com" crossorigin>
    @vite(['resources/css/app.css'])
</head>
<body hx-boost="true">
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
@vite(['resources/js/app.js'])
</body>
</html>
