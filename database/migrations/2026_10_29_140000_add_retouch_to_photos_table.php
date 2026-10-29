<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retouche non destructive : on enregistre les réglages, jamais une image
 * modifiée à la place de l'original. Les dérivés se refabriquent à partir de
 * l'original et des réglages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            // {coins, quart, focale, luminosite, contraste, largeur_px, hauteur_px}
            $table->json('retouch')->nullable()->after('sheet_box');

            // Version web non retouchée, conservée pour pouvoir reprendre la
            // retouche : c'est sur elle qu'on replace les coins.
            $table->string('raw_web_path')->nullable()->after('web_path');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn(['retouch', 'raw_web_path']);
        });
    }
};
