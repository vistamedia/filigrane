<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Attribue les numéros d'inventaire « FIL-0001 », « FIL-0002 »…
 *
 * Les numéros ne sont jamais recyclés : une fiche mise à la corbeille garde le
 * sien, sinon deux dessins différents porteraient la même référence dans les
 * notes papier.
 */
class InventoryNumberGenerator
{
    public function next(): string
    {
        $prefix = config('filigrane.inventory_prefix', 'FIL');
        $padding = (int) config('filigrane.inventory_padding', 4);

        return DB::transaction(function () use ($prefix, $padding): string {
            // On interroge la table directement pour inclure les fiches en
            // corbeille, dont les numéros restent réservés.
            $highest = DB::table('drawings')
                ->where('inventory_number', 'like', $prefix.'-%')
                ->lockForUpdate()
                ->selectRaw('MAX(CAST(SUBSTRING(inventory_number, ?) AS UNSIGNED)) AS highest', [strlen($prefix) + 2])
                ->value('highest');

            return sprintf('%s-%s', $prefix, str_pad((string) ((int) $highest + 1), $padding, '0', STR_PAD_LEFT));
        });
    }
}
