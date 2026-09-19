<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <base href="/pos-kantin/">
    <title>Kantin RSIA Aisyiyah Pekajangan</title>

    <!-- PWA & Mobile Web App Meta -->
    <link rel="manifest" href="/pos-kantin/manifest.json?v=5">
    <meta name="theme-color" content="#059669">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Kantin RSIA">
    <link rel="apple-touch-icon" href="/pos-kantin/images/logo-512.png?v=5">

    <!-- Favicon -->
    <link rel="shortcut icon" href="/pos-kantin/favicon.ico?v=5">
    <link rel="icon" type="image/png" sizes="64x64" href="/pos-kantin/images/favicon.png?v=5">
    <link rel="icon" type="image/png" sizes="32x32" href="/pos-kantin/images/favicon-32.png?v=5">
    <link rel="icon" type="image/svg+xml" href="/pos-kantin/images/favicon.svg?v=5">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-900 selection:bg-emerald-500 selection:text-white">
    @inertia

    <!-- PWA Service Worker Registration & Cache Refresh -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/pos-kantin/sw.js?v=5', { scope: '/pos-kantin/' })
                    .then(reg => {
                        reg.update();
                        console.log('PWA Service Worker active:', reg.scope);
                    })
                    .catch(err => console.log('PWA SW registration notice:', err));
            });
        }
    </script>
</body>
</html>
