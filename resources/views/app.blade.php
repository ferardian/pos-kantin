<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Trisna Jaya Listrik</title>

    <!-- PWA & Mobile Web App Meta -->
    <link rel="manifest" href="/manifest.json?v=2">
    <meta name="theme-color" content="#059669">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Trisna Jaya">
    <link rel="apple-touch-icon" href="/images/logo-512.png?v=2">

    <!-- Favicon (Exact Original Logo with High Contrast Badge for Dark & Light Browser Tabs) -->
    <link rel="icon" type="image/png" href="/images/favicon.png?v=2">
    <link rel="icon" type="image/svg+xml" href="/images/favicon.svg?v=2">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-900 selection:bg-amber-500 selection:text-white">
    @inertia

    <!-- PWA Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(reg => console.log('PWA Service Worker registered:', reg.scope))
                    .catch(err => console.log('PWA SW registration failed:', err));
            });
        }
    </script>
</body>
</html>
