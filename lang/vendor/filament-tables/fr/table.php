<?php

/*
 * Compléments à la traduction française de Filament Tables 5.11.
 */

return [

    'column_manager' => [
        'position' => 'Position :position sur :count.',
        'position_in_group' => 'Position :position sur :count dans :group.',
        'reordered' => ':column déplacée en position :position sur :count.',
        'reordered_deferred' => ':column déplacée en position :position sur :count. Appliquez les colonnes pour enregistrer.',
        'reordered_in_group' => ':column déplacée en position :position sur :count dans :group.',
        'reordered_in_group_deferred' => ':column déplacée en position :position sur :count dans :group. Appliquez les colonnes pour enregistrer.',

        'actions' => [
            'move_up' => ['label' => 'Remonter :column'],
            'move_down' => ['label' => 'Descendre :column'],
            'reorder' => ['label' => 'Réorganiser la colonne'],
        ],
    ],

    'columns' => [
        'editable' => [
            'saving' => 'Enregistrement…',
            'saved' => 'Modification enregistrée.',
            'failure' => 'L\'enregistrement n\'a pas pu être confirmé. Rechargez la page avant de réessayer.',
            'unavailable' => 'Cet élément ne peut plus être modifié.',
        ],

        'icon' => [
            'boolean' => [
                'true' => 'Oui',
                'false' => 'Non',
            ],
        ],

        'select' => [
            'loading_message' => 'Chargement…',
            'no_options_message' => 'Aucun choix disponible.',
            'no_search_results_message' => 'Aucun choix ne correspond à votre recherche.',
            'placeholder' => 'Choisissez',
            'searching_message' => 'Recherche en cours…',
            'search_prompt' => 'Saisissez pour rechercher',
        ],
    ],

    'actions' => [
        'reorder_record' => ['label' => 'Réorganiser l\'élément :key'],
        'move_record_up' => ['label' => 'Remonter :label'],
        'move_record_down' => ['label' => 'Descendre :label'],
        'toggle_record_content' => ['label' => 'Déplier ou replier l\'élément :key'],
    ],

    'loading' => 'Chargement…',

    'reordering' => [
        'position' => ':label, position :position sur :count.',
        'moved' => ':label déplacé en position :position sur :count.',
    ],

    'result_count' => '{0} Aucun résultat|{1} :count résultat|[2,*] :count résultats',

];
