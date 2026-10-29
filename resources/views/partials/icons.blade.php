@php($description = __('Two days of diplomacy, debate and negotiation across UNW, UNSC, PNA and a Special Committee. :dates, online.', ['dates' => config('conference.dates_label')]))

<meta name="description" content="{{ $description }}">
<meta name="theme-color" content="#3b0a6b">
<link rel="canonical" href="{{ url()->current() }}">
@unless (request()->routeIs('home', 'register', 'links'))
    <meta name="robots" content="noindex">
@endunless

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon-32.png" type="image/png" sizes="32x32">
<link rel="icon" href="/icon-192.png" type="image/png" sizes="192x192">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

{{-- Link previews (WhatsApp, Instagram, Facebook, X) --}}
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('conference.name') }}">
<meta property="og:title" content="{{ config('conference.name') }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ asset('og-image.jpg') }}">
<meta property="og:image:type" content="image/jpeg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ __('Homebridge MUN ’26 crest') }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ config('conference.name') }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ asset('og-image.jpg') }}">

{{-- Search engine site name and logo --}}
@if (request()->routeIs('home'))
@verbatim
<script type="application/ld+json">
{"@context":"https://schema.org","@graph":[{"@type":"WebSite","name":"Homebridge MUN ’26","alternateName":"Homebridge MUN","url":"https://hbmun.info/"},{"@type":"Organization","name":"Homebridge MUN","url":"https://hbmun.info/","logo":"https://hbmun.info/icon-512.png"}]}
</script>
@endverbatim
@endif
