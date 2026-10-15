<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 16);
            $table->text('reason');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('lifted_at')->nullable();
            $table->foreignId('lifted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'lifted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctions');
    }
};
