<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            // Famille facultative : permet de regrouper les mots-clés plus tard
            // (école, sujet, technique, emplacement...) sans refaire le schéma.
            $table->string('family', 50)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('drawing_tag', function (Blueprint $table) {
            $table->foreignId('drawing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['drawing_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drawing_tag');
        Schema::dropIfExists('tags');
    }
};
