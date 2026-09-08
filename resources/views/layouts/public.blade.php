<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ACL — Anyone Can Learn')</title>
    <meta name="description" content="@yield('description', 'ACL — Anyone Can Learn. Open learning for Nigerian institutions. Your courses follow your enrolment, not your wallet.')">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('title', 'ACL — Anyone Can Learn')">
    <meta property="og:description" content="@yield('description', 'ACL connects learners, educators, courses, resources, and universities in one structured learning ecosystem.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="@yield('title', 'ACL — Anyone Can Learn')">
    <meta name="twitter:description" content="@yield('description', 'ACL connects learners, educators, courses, resources, and universities in one structured learning ecosystem.')">
    @include('partials.theme-script')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-bg font-display text-text antialiased">
    <x-site-header />
    <main>
        @yield('content')
    </main>
    <x-site-footer />
</body>
</html>
