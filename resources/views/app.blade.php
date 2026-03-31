<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title inertia>{{ config('app.name', 'Learn247') }}</title>
    <link rel="icon" type="image/png" href="/brand/ICON/CURZZO LOGO WHIT BG ROUND.png" />
    @if(isset($ogMeta))
        <meta property="og:type" content="website" />
        <meta property="og:title" content="{{ $ogMeta['title'] }}" />
        <meta property="og:description" content="{{ $ogMeta['description'] }}" />
        <meta property="og:url" content="{{ $ogMeta['url'] }}" />
        @if($ogMeta['image'])
            <meta property="og:image" content="{{ $ogMeta['image'] }}" />
        @endif
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
