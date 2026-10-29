<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profils d'échelle : « pour cet appareil, à cette focale, dans ce cadrage,
     * 1 mm réel vaut N pixels ». Établi une seule fois en photographiant une
     * référence de longueur connue (règle, carte bancaire), puis réutilisé
     * automatiquement sur toutes les photos de la même série.
     */
    public function up(): void
    {
        Schema::create('calibrations', function (Blueprint $table) {
            $table->id();

            $table->string('label');
            $table->boolean('is_active')->default(true);

            // Signature de la série de prises de vue, pour reconnaître les photos
            // qui peuvent réutiliser cette échelle.
            $table->string('camera')->nullable()->index();
            $table->unsignedSmallInteger('focal_35mm')->nullable();
            $table->unsignedInteger('image_width_px')->nullable();
            $table->unsignedInteger('image_height_px')->nullable();

            // Résultat de la calibration.
            $table->decimal('px_per_mm', 12, 6);

            // Trace de la mesure d'origine : référence utilisée et segment tracé.
            $table->string('reference_label')->nullable();
            $table->decimal('reference_mm', 10, 2)->nullable();
            $table->json('reference_segment')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibrations');
    }
};
