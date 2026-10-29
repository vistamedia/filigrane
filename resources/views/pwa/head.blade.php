{{-- Déclaration de l'application installable, injectée dans le <head> du panneau. --}}
<link rel="manifest" href="/manifest.webmanifest">
{{-- Teinte claire : l'application est sur fond papier, et l'icône aussi. --}}
<meta name="theme-color" content="#FBF8F2">
<meta name="application-name" content="Filigrane">
<meta name="mobile-web-app-capable" content="yes">

{{-- iOS : écran d'accueil, plein écran, barre d'état sombre. --}}
<meta name="apple-mobile-web-app-capable" content="yes">
{{-- « default » et non « black-translucent » : le contenu reste sous la barre
     d'état, sans risque de chevauchement avec la barre de navigation. --}}
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Filigrane">
<link rel="apple-touch-icon" href="/icones/icone-180x180.png">

<link rel="icon" type="image/svg+xml" href="/icones/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="/icones/icone-32x32.png">
<link rel="alternate icon" href="/icones/favicon.ico">

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch(() => {
                // Un service worker indisponible n'empêche pas d'utiliser l'application.
            });
        });
    }
</script>
