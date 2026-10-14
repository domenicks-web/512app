<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specimen_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('specimen_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('trade_id')->constrained()->cascadeOnDelete();
            $table->timestamp('transferred_at');
            $table->index(['specimen_id', 'transferred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specimen_transfers');
    }
};
