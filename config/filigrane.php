<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Numéros d'inventaire
    |--------------------------------------------------------------------------
    | Les fiches reçoivent un numéro séquentiel « PRÉFIXE-0001 » à la création.
    */

    'inventory_prefix' => env('FILIGRANE_INVENTORY_PREFIX', 'FIL'),
    'inventory_padding' => 4,

    /*
    |--------------------------------------------------------------------------
    | Traitement des images
    |--------------------------------------------------------------------------
    | 'sync'  : les dérivés sont fabriqués pendant la requête d'upload.
    |           Chaque fichier arrive dans sa propre requête (Livewire), donc
    |           même un import de 200 photos ne dépasse aucune limite.
    | 'queue' : les dérivés partent en file d'attente (worker ou cron requis).
    */

    'image_processing' => env('FILIGRANE_IMAGE_PROCESSING', 'sync'),

    'web_width' => (int) env('FILIGRANE_WEB_WIDTH', 2400),
    'thumb_size' => (int) env('FILIGRANE_THUMB_SIZE', 600),
    'jpeg_quality' => (int) env('FILIGRANE_JPEG_QUALITY', 82),

    /*
    |--------------------------------------------------------------------------
    | Stockage
    |--------------------------------------------------------------------------
    | Les originaux restent sur un disque privé (route authentifiée) ; seuls
    | les dérivés JPEG sont servis en statique par Apache.
    */

    'originals_disk' => 'local',
    'originals_path' => 'photos/originaux',
    'derivatives_disk' => 'public',
    'web_path' => 'photos/web',
    'thumb_path' => 'photos/miniatures',

    'accepted_mimes' => [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
        'image/heif',
        'image/tiff',
        'image/avif',
    ],

    /*
    |--------------------------------------------------------------------------
    | Références d'échelle proposées dans l'outil de calibration
    |--------------------------------------------------------------------------
    | Longueurs normalisées, en millimètres.
    */

    'scale_references' => [
        ['label' => 'Carte bancaire (côté long)', 'mm' => 85.6],
        ['label' => 'Carte bancaire (côté court)', 'mm' => 53.98],
        ['label' => 'Règle — 10 cm', 'mm' => 100.0],
        ['label' => 'Règle — 20 cm', 'mm' => 200.0],
        ['label' => 'Feuille A4 (hauteur)', 'mm' => 297.0],
        ['label' => 'Feuille A4 (largeur)', 'mm' => 210.0],
        ['label' => 'Pièce de 2 € (diamètre)', 'mm' => 25.75],
    ],

    /*
    |--------------------------------------------------------------------------
    | Zooms de l'appareil, proposés dans l'outil de retouche
    |--------------------------------------------------------------------------
    | Focale équivalente 24 × 36, en millimètres, de chaque position du zoom de
    | l'iPhone. Elle sert à retrouver les vraies proportions d'un cadre
    | photographié de biais (voir App\Support\Perspective) ; les photos prises
    | depuis Filigrane arrivent sans EXIF, il faut donc la demander.
    */

    'zooms' => [
        ['label' => '0,5×', 'focale' => 13],
        ['label' => '1×', 'focale' => 26],
        ['label' => '2×', 'focale' => 52],
        ['label' => '3×', 'focale' => 77],
        ['label' => '5×', 'focale' => 120],
    ],

    /*
    |--------------------------------------------------------------------------
    | Détection automatique de la feuille
    |--------------------------------------------------------------------------
    | Tolérance de couleur du fond, en pourcentage, utilisée pour détourer la
    | feuille sur un fond uni.
    */

    'sheet_detection_fuzz' => 22,

];
