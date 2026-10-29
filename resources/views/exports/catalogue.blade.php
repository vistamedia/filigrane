{{--
    Catalogue imprimable. Rendu par dompdf, qui ne connaît que CSS 2.1 :
    ni flexbox, ni grille, ni unités modernes. Tout tient donc en blocs,
    en points et en millimètres.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Filigrane — catalogue</title>
    <style>
        @page {
            margin: 18mm 20mm 20mm;
        }

        body {
            font-family: 'DejaVu Serif', serif;
            font-size: 10.5pt;
            line-height: 1.5;
            color: #1C1A17;
            margin: 0;
        }

        .garde {
            page-break-after: always;
            padding-top: 70mm;
            text-align: center;
        }

        .garde h1 {
            font-size: 30pt;
            font-weight: normal;
            letter-spacing: 0.06em;
            margin: 0 0 6mm;
        }

        .garde .sous {
            font-size: 12pt;
            color: #6B625A;
            margin: 0 0 24mm;
        }

        .garde .filet {
            width: 36mm;
            border-top: 1px solid #8E462F;
            margin: 0 auto 24mm;
        }

        .garde .pied {
            font-size: 9.5pt;
            color: #6B625A;
        }

        .planche {
            page-break-after: always;
        }

        .planche:last-child {
            page-break-after: avoid;
        }

        .scene {
            height: 112mm;
            text-align: center;
        }

        .scene img {
            border: 0.4pt solid #CFC6B8;
        }

        .sans-image {
            height: 112mm;
            text-align: center;
            color: #9A9188;
            font-size: 10pt;
            font-style: italic;
            padding-top: 50mm;
        }

        .cote {
            font-size: 9pt;
            letter-spacing: 0.12em;
            color: #8E462F;
            margin: 8mm 0 1mm;
        }

        h2 {
            font-size: 15pt;
            font-weight: normal;
            margin: 0 0 1mm;
        }

        .meta {
            font-size: 10pt;
            color: #6B625A;
            margin: 0 0 5mm;
        }

        .texte p {
            margin: 0 0 2.5mm;
        }

        .texte ul,
        .texte ol {
            margin: 0 0 2.5mm;
            padding-left: 6mm;
        }

        .note {
            margin-top: 6mm;
            padding: 3mm 0 0 4mm;
            border-left: 1pt solid #E0CFC4;
            font-size: 9.5pt;
            color: #4A423B;
        }

        .note .intitule {
            font-size: 8pt;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #9A9188;
            margin: 0 0 1mm;
        }

        .mots {
            margin-top: 6mm;
            font-size: 9pt;
            color: #6B625A;
        }
    </style>
</head>
<body>
    <div class="garde">
        <h1>Filigrane</h1>
        <p class="sous">Catalogue d'une collection de dessins anciens</p>
        <div class="filet"></div>
        <p class="pied">
            {{ count($planches) }} {{ count($planches) > 1 ? 'dessins' : 'dessin' }}<br>
            {{ $date->locale('fr')->isoFormat('D MMMM YYYY') }}
        </p>
    </div>

    @foreach ($planches as $planche)
        <div class="planche">
            @if ($planche['image'])
                <div class="scene">
                    <img
                        src="{{ $planche['image']['chemin'] }}"
                        style="width: {{ $planche['image']['largeur'] }}mm; height: {{ $planche['image']['hauteur'] }}mm;"
                        alt=""
                    >
                </div>
            @else
                <div class="sans-image">Aucune photographie</div>
            @endif

            <p class="cote">{{ $planche['cote'] }}</p>

            <h2>{{ $planche['artiste'] ?: 'Artiste non identifié' }}</h2>

            <p class="meta">
                {{ collect([$planche['periode'], $planche['dimensions']])->filter()->implode(' · ') ?: '—' }}
            </p>

            <div class="texte">{!! $planche['description'] !!}</div>

            @if ($planche['note'])
                <div class="note">
                    <p class="intitule">Note</p>
                    {!! $planche['note'] !!}
                </div>
            @endif

            @if ($planche['motsCles'])
                <p class="mots">{{ $planche['motsCles'] }}</p>
            @endif
        </div>
    @endforeach
</body>
</html>
