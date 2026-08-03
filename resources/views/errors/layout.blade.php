<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#173d3a">
    <title>@yield('title', 'Terjadi masalah') · LibSync</title>
    <script>try { document.documentElement.dataset.theme = localStorage.getItem('library-theme') || 'light'; } catch (_) {}</script>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">
    <link rel="stylesheet" href="{{ asset('css/dark-mode.css') }}?v=20260802-2">
    <link rel="stylesheet" href="{{ asset('css/error-pages.css') }}?v=20260803-2">
</head>
<body class="error-body">
    <main class="error-page" aria-labelledby="errorTitle">
        <a class="error-page__brand" href="{{ route('login') }}" aria-label="Kembali ke LibSync">
            <img src="{{ asset('images/libsync-logo-512.png') }}" alt="">
            <strong>LibSync</strong>
        </a>
        @yield('content')
    </main>
</body>
</html>
