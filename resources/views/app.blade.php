<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#F39200">
    <title>Suivi d'efficacité — Fondation EPI</title>
    <link rel="icon" href="/images/logo-epi.png" type="image/png">
    {{-- Thème appliqué avant le premier rendu pour éviter un flash clair/sombre --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('epi.theme');
                if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t;
            } catch (e) {}
        })();
    </script>
    {{-- Polices déclarées dans vite.config.js (bunny Poppins), servies localement : pas d'appel à un CDN tiers --}}
    {{ Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    <div id="app"></div>
</body>
</html>
