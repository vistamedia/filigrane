<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drawing_id')->constrained()->cascadeOnDelete();

            // recto, verso, detail, frame, document
            $table->string('kind', 20)->default('recto');
            $table->string('caption')->nullable();

            // L'original est conservé tel quel, hors du dossier public.
            $table->string('original_path');
            $table->string('original_name')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('width_px')->nullable();
            $table->unsignedInteger('height_px')->nullable();

            // Dérivés JPEG servis par Apache depuis le dossier public.
            $table->string('web_path')->nullable();
            $table->string('thumb_path')->nullable();

            $table->json('exif')->nullable();
            $table->timestamp('taken_at')->nullable();

            // Échelle appliquée à cette photo et géométrie mesurée dessus.
            $table->foreignId('calibration_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('px_per_mm', 12, 6)->nullable();
            $table->json('sheet_box')->nullable(); // {x,y,w,h} en coordonnées normalisées 0..1

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->text('processing_error')->nullable();

            $table->timestamps();

            $table->index(['drawing_id', 'sort_order']);
        });

        Schema::table('drawings', function (Blueprint $table) {
            $table->foreignId('cover_photo_id')->nullable()->after('status')
                ->constrained('photos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('drawings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cover_photo_id');
        });

        Schema::dropIfExists('photos');
    }
};
