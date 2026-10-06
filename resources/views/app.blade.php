<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">

    @php
        $siteProps = $page['props']['site'] ?? [];
        $appearanceProps = is_array($siteProps) ? ($siteProps['appearance'] ?? []) : [];
        $generalProps = is_array($siteProps) ? ($siteProps['general'] ?? []) : [];
        $pwaThemeColor = is_array($appearanceProps)
            ? ($appearanceProps['primary_color'] ?? '#2563EB')
            : '#2563EB';
        $pwaName = is_array($generalProps)
            ? ($generalProps['short_name'] ?? $generalProps['site_name'] ?? config('app.name', 'Digital Library'))
            : config('app.name', 'Digital Library');
        $serverSeo = $page['props']['seo'] ?? null;
        $serverTitle = is_array($serverSeo)
            ? ($serverSeo['title'] ?? config('app.name', 'Digital Library'))
            : config('app.name', 'Digital Library');
    @endphp

    <meta name="theme-color" content="{{ $pwaThemeColor }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ $pwaName }}">
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="/pwa/apple-touch-icon.png">

    <title data-inertia="">{{ $serverTitle }}</title>

    @if (is_array($serverSeo))
        <meta data-inertia="description" name="description" content="{{ $serverSeo['description'] ?? '' }}">
        <meta data-inertia="robots" name="robots" content="{{ $serverSeo['robots'] ?? 'index,follow' }}">
        <link data-inertia="canonical" rel="canonical" href="{{ $serverSeo['canonical'] ?? url()->current() }}">

        @if (! empty($serverSeo['google_site_verification']))
            <meta
                data-inertia="google-site-verification"
                name="google-site-verification"
                content="{{ $serverSeo['google_site_verification'] }}"
            >
        @endif

        @php($openGraph = $serverSeo['open_graph'] ?? [])
        <meta data-inertia="og:type" property="og:type" content="{{ $openGraph['type'] ?? 'website' }}">
        @if (! empty($openGraph['locale']))
            <meta data-inertia="og:locale" property="og:locale" content="{{ $openGraph['locale'] }}">
        @endif
        @if (! empty($openGraph['site_name']))
            <meta data-inertia="og:site_name" property="og:site_name" content="{{ $openGraph['site_name'] }}">
        @endif
        <meta data-inertia="og:title" property="og:title" content="{{ $openGraph['title'] ?? $serverTitle }}">
        <meta data-inertia="og:description" property="og:description" content="{{ $openGraph['description'] ?? ($serverSeo['description'] ?? '') }}">
        @if (! empty($openGraph['url']))
            <meta data-inertia="og:url" property="og:url" content="{{ $openGraph['url'] }}">
        @endif
        @if (! empty($openGraph['image']))
            <meta data-inertia="og:image" property="og:image" content="{{ $openGraph['image'] }}">
            @if (! empty($openGraph['image_alt']))
                <meta data-inertia="og:image:alt" property="og:image:alt" content="{{ $openGraph['image_alt'] }}">
            @endif
        @endif

        @php($twitter = $serverSeo['twitter'] ?? [])
        <meta data-inertia="twitter:card" name="twitter:card" content="{{ $twitter['card'] ?? 'summary' }}">
        <meta data-inertia="twitter:title" name="twitter:title" content="{{ $twitter['title'] ?? $serverTitle }}">
        <meta data-inertia="twitter:description" name="twitter:description" content="{{ $twitter['description'] ?? ($serverSeo['description'] ?? '') }}">
        @if (! empty($twitter['image']))
            <meta data-inertia="twitter:image" name="twitter:image" content="{{ $twitter['image'] }}">
            @if (! empty($twitter['image_alt']))
                <meta data-inertia="twitter:image:alt" name="twitter:image:alt" content="{{ $twitter['image_alt'] }}">
            @endif
        @endif

        @foreach (($serverSeo['json_ld'] ?? []) as $node)
            <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}" data-inertia="jsonld-{{ $loop->index }}" type="application/ld+json">{!! json_encode(
                $node,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
            ) !!}</script>
        @endforeach
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @inertiaHead
</head>
<body class="min-h-screen bg-background font-sans text-foreground antialiased">
    @inertia
</body>
</html>
