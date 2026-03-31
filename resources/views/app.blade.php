<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title inertia>{{ config('app.name', 'Learn247') }}</title>
    <link rel="icon" type="image/png" href="/brand/ICON/CURZZO LOGO WHIT BG ROUND.png" />
    @php $og = isset($page['props']['ogMeta']) ? $page['props']['ogMeta'] : null; @endphp
    @if($og)
        <meta property="og:type" content="website" />
        <meta property="og:title" content="{{ $og['title'] }}" />
        <meta property="og:description" content="{{ $og['description'] }}" />
        <meta property="og:url" content="{{ $og['url'] }}" />
        @if($og['image'])
            <meta property="og:image" content="{{ $og['image'] }}" />
        @endif
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
