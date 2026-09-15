<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specimens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('species_id');
            $table->foreign('species_id')->references('id')->on('species')->restrictOnDelete();
            $table->string('seed', 64);
            $table->boolean('is_shiny')->default(false);
            $table->decimal('size_roll', 6, 5);
            $table->string('size_class');
            $table->string('nature');
            $table->unsignedTinyInteger('iv_hp');
            $table->unsignedTinyInteger('iv_atk');
            $table->unsignedTinyInteger('iv_def');
            $table->unsignedTinyInteger('iv_spa');
            $table->unsignedTinyInteger('iv_spd');
            $table->unsignedTinyInteger('iv_spe');
            $table->unsignedSmallInteger('iv_total')->storedAs(
                'iv_hp + iv_atk + iv_def + iv_spa + iv_spd + iv_spe'
            );
            $table->unsignedInteger('mint_number');
            $table->string('hash');
            $table->string('nickname', 16)->nullable();
            $table->timestamp('caught_at');
            $table->timestamps();

            $table->unique(['species_id', 'mint_number']);
            $table->index(['user_id', 'species_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specimens');
    }
};
