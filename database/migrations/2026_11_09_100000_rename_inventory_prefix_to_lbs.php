<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Les numéros d'inventaire passent de « FIL- » à « LBS- », le 9 novembre 2026,
 * à la demande d'Emmanuel : la collection s'appelle LBS. Voir D-46.
 *
 * Seul le préfixe change : le numéro et ses suffixes saisis à la main
 * (« FIL-0019a » devient « LBS-0019a ») restent tels quels. Un numéro qui ne
 * commence pas par « FIL- » n'est pas touché. Les fiches en corbeille suivent
 * aussi, puisque leurs numéros restent réservés (D-19).
 *
 * Au passage, l'espace qu'un numéro portait en fin (« FIL-0036 ») disparaît :
 * le champ, qui la laissait passer, la retire désormais à l'enregistrement.
 *
 * La date de modification des fiches n'est pas touchée : changer de préfixe
 * n'est pas retravailler la fiche.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->remplacer('FIL-', 'LBS-');
    }

    public function down(): void
    {
        $this->remplacer('LBS-', 'FIL-');
    }

    private function remplacer(string $ancien, string $nouveau): void
    {
        DB::transaction(function () use ($ancien, $nouveau): void {
            $fiches = DB::table('drawings')
                ->where('inventory_number', 'like', $ancien.'%')
                ->get(['id', 'inventory_number']);

            foreach ($fiches as $fiche) {
                $numero = trim($fiche->inventory_number);

                // « like » ignore la casse : « fil-12 » n'est pas un numéro attribué.
                if (! str_starts_with($numero, $ancien)) {
                    continue;
                }

                DB::table('drawings')
                    ->where('id', $fiche->id)
                    ->update(['inventory_number' => $nouveau.substr($numero, strlen($ancien))]);
            }
        });
    }
};
