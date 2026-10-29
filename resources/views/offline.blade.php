<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Filigrane — pas de connexion</title>
    <link rel="icon" type="image/svg+xml" href="/icones/favicon.svg">
    <style>
        :root {
            --encre: #1C1A17;
            --papier: #FBF8F2;
            --sanguine: #A3553E;
            --gris: #6B6358;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100dvh;
            display: grid;
            place-items: center;
            padding: 2rem 1rem;
            background: var(--papier);
            color: var(--encre);
            font-family: ui-serif, Georgia, "Times New Roman", serif;
            font-size: 1.25rem;
            line-height: 1.6;
        }

        main {
            max-width: 32rem;
            text-align: center;
        }

        img { width: 88px; height: 88px; }

        h1 {
            font-size: 1.75rem;
            margin: 1.5rem 0 0.75rem;
            font-weight: 600;
        }

        p { color: var(--gris); margin: 0 0 1.75rem; }

        button {
            font: inherit;
            font-size: 1.125rem;
            padding: 0.85rem 1.75rem;
            min-height: 48px;
            border: 0;
            border-radius: 10px;
            background: var(--sanguine);
            color: #fff;
            cursor: pointer;
        }

        button:hover { background: #8E462F; }

        @media (prefers-color-scheme: dark) {
            body { background: var(--encre); color: var(--papier); }
            p { color: #A89E90; }
        }
    </style>
</head>
<body>
    <main>
        <img src="/icones/icone-192x192.png" alt="">
        <h1>Pas de connexion</h1>
        <p>
            Filigrane a besoin d'Internet pour afficher la collection.
            Vérifiez le Wi-Fi ou les données mobiles, puis réessayez.
        </p>
        <button onclick="location.reload()">Réessayer</button>
    </main>
</body>
</html>
