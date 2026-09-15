<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('client_seed')->after('email');
            $table->unsignedInteger('nonce')->default(0)->after('client_seed');
            $table->timestamp('next_pack_at')->nullable()->after('nonce');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['client_seed', 'nonce', 'next_pack_at']);
        });
    }
};
