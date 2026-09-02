<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ACL — Anyone Can Learn')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-abyss text-slate-200 font-display antialiased">
    <div class="min-h-screen flex items-center justify-center px-4"
         style="background-image: radial-gradient(circle at 50% 0%, rgba(34,211,238,0.07), transparent 60%), linear-gradient(rgba(31,44,74,0.25) 1px, transparent 1px), linear-gradient(90deg, rgba(31,44,74,0.25) 1px, transparent 1px); background-size: 100% 100%, 32px 32px, 32px 32px;">
        @yield('content')
    </div>
</body>
</html>
