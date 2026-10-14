<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Abertura do pack: a seed do próximo pack já comprometida no usuário
 * (commit antes, reveal depois), as bolas abertas de cada pack e o
 * momento em que cada espécime foi revelado. Os espécimes que já
 * existirem contam como revelados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('next_server_seed')->nullable()->after('nonce');
            $table->string('next_server_seed_hash', 64)->nullable()->after('next_server_seed');
        });

        Schema::table('openings', function (Blueprint $table) {
            $table->unsignedTinyInteger('opened_slots')->default(0)->after('nonce');
        });

        Schema::table('specimens', function (Blueprint $table) {
            $table->unsignedTinyInteger('slot')->default(1)->after('opening_id');
            $table->timestamp('revealed_at')->nullable()->after('caught_at');
        });

        DB::table('specimens')->update(['revealed_at' => DB::raw('caught_at')]);
    }

    public function down(): void
    {
        Schema::table('specimens', function (Blueprint $table) {
            $table->dropColumn(['slot', 'revealed_at']);
        });

        Schema::table('openings', function (Blueprint $table) {
            $table->dropColumn('opened_slots');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['next_server_seed', 'next_server_seed_hash']);
        });
    }
};
