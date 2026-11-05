<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Où se trouve le dessin : un carton ou une pièce de la maison. La liste est
 * fixe (App\Enums\Location) ; « Autre » s'accompagne d'une précision libre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drawings', function (Blueprint $table) {
            // box_1 … hallway, other. Null : emplacement non renseigné.
            $table->string('location', 20)->nullable()->after('status')->index();
            $table->string('location_other')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('drawings', function (Blueprint $table) {
            $table->dropIndex(['location']);
            $table->dropColumn(['location', 'location_other']);
        });
    }
};
