<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('species', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type_1');
            $table->string('type_2')->nullable();
            $table->string('rarity_tier');
            $table->unsignedSmallInteger('base_stat_total');
            $table->unsignedTinyInteger('evolution_stage');
            $table->decimal('base_height_m', 4, 2);
            $table->decimal('base_weight_kg', 6, 2);
            $table->string('sprite_path');
            $table->string('sprite_shiny_path');
            $table->string('artwork_path');
            $table->text('flavor_pt')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('species');
    }
};
