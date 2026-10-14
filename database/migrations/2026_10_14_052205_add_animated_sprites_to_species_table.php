<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sprites animados (black-white) usados na abertura do pack; o PNG é o fallback. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('species', function (Blueprint $table) {
            $table->string('sprite_animated_path')->nullable()->after('sprite_shiny_path');
            $table->string('sprite_animated_shiny_path')->nullable()->after('sprite_animated_path');
        });
    }

    public function down(): void
    {
        Schema::table('species', function (Blueprint $table) {
            $table->dropColumn(['sprite_animated_path', 'sprite_animated_shiny_path']);
        });
    }
};
