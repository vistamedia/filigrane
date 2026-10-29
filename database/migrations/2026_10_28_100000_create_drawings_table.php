<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drawings', function (Blueprint $table) {
            $table->id();

            // Numéro d'inventaire généré automatiquement (FIL-0001, FIL-0002, ...).
            $table->string('inventory_number', 32)->unique();

            // Les quatre champs demandés au départ.
            $table->string('artist')->nullable()->index();
            $table->string('period')->nullable()->index();
            $table->longText('description')->nullable(); // HTML (éditeur enrichi)
            $table->longText('note')->nullable();        // HTML (éditeur enrichi)

            // Dimensions de la feuille, en millimètres entiers.
            $table->unsignedInteger('width_mm')->nullable();
            $table->unsignedInteger('height_mm')->nullable();
            // Provenance de la mesure : estimated (calculée depuis la photo),
            // measured (mesurée physiquement), manual (saisie à la main).
            $table->string('dimensions_source', 20)->nullable();

            // Avancement du catalogage : to_describe, in_progress, described
            $table->string('status', 20)->default('to_describe')->index();
            $table->timestamp('described_at')->nullable();

            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drawings');
    }
};
