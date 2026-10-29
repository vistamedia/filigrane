<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un cliché à refaire : on le garde en attendant le nouveau — il suffit à
 * décrire l'œuvre —, mais on note pourquoi il ne convient pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            // reflections, blur, dark, framing, other. Null : le cliché convient.
            $table->string('reshoot_reason', 20)->nullable()->after('retouch')->index();
            $table->string('reshoot_note')->nullable()->after('reshoot_reason');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropIndex(['reshoot_reason']);
            $table->dropColumn(['reshoot_reason', 'reshoot_note']);
        });
    }
};
