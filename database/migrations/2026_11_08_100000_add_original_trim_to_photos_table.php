<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Original allégé : rogné, pour de bon, autour de l'œuvre (D-44).
 *
 * On retient où la partie gardée se trouvait dans la photographie entière, et
 * la taille de celle-ci. Le calcul des vraies proportions en a besoin : il
 * place le centre optique au centre de la photographie entière, et tire la
 * focale de sa diagonale. Sur l'original rogné, les deux seraient faux.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            // {x, y, largeur_px, hauteur_px} : position de l'original allégé
            // dans la photographie entière, et taille de celle-ci.
            $table->json('original_trim')->nullable()->after('retouch');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn('original_trim');
        });
    }
};
