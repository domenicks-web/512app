<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convite infinito deixa de existir (o painel vai de 1 a 10 usos). Os que
 * sobrarem sem limite viram o teto atual de usos, pra continuar valendo.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('invites')->whereNull('max_uses')->update(['max_uses' => 10]);

        Schema::table('invites', function (Blueprint $table) {
            $table->unsignedInteger('max_uses')->default(1)->nullable(false)->change();
            $table->foreignId('revoked_by')->nullable()->after('revoked_at')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('invites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revoked_by');
            $table->unsignedInteger('max_uses')->nullable()->default(null)->change();
        });
    }
};
