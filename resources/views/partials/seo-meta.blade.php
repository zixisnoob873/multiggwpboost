@php
    $seo = is_array($seo ?? null) ? $seo : [];
    $seoDescription = trim((string) ($seo['description'] ?? ''));
    $seoCanonical = trim((string) ($seo['canonical'] ?? ''));
    $seoRobots = trim((string) ($seo['robots'] ?? ''));
    $seoTitle = trim((string) ($seo['title'] ?? ''));
    $seoType = trim((string) ($seo['type'] ?? 'website')) ?: 'website';
    $seoImage = trim((string) ($seo['image'] ?? ''));
    $seoTwitterCard = trim((string) ($seo['twitter_card'] ?? '')) ?: ($seoImage !== '' ? 'summary_large_image' : 'summary');
    $seoSchema = $seo['schema'] ?? null;
@endphp

@if($seoDescription !== '')
    <meta name="description" content="{{ $seoDescription }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
@endif

@if($seoTitle !== '')
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta name="twitter:title" content="{{ $seoTitle }}">
@endif

@if($seoCanonical !== '')
    <link rel="canonical" href="{{ $seoCanonical }}">
    <meta property="og:url" content="{{ $seoCanonical }}">
@endif

@if($seoRobots !== '')
    <meta name="robots" content="{{ $seoRobots }}">
@endif

<meta property="og:type" content="{{ $seoType }}">
<meta name="twitter:card" content="{{ $seoTwitterCard }}">
<meta property="og:site_name" content="{{ config('app.name', 'GGWP-Boost') }}">

@if($seoImage !== '')
    <meta property="og:image" content="{{ $seoImage }}">
    <meta name="twitter:image" content="{{ $seoImage }}">
@endif

@if(! empty($seo['published_time']))
    <meta property="article:published_time" content="{{ $seo['published_time'] }}">
@endif

@if(! empty($seo['modified_time']))
    <meta property="article:modified_time" content="{{ $seo['modified_time'] }}">
@endif

@if(! empty($seoSchema))
    <script nonce="{{ $cspNonce ?? '' }}" type="application/ld+json">{!! json_encode(
        $seoSchema,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRETTY_PRINT
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    ) !!}</script>
@endif
