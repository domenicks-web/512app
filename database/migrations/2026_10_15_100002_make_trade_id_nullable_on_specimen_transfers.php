<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Transferência sem troca é uma libertação (banimento). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('specimen_transfers', function (Blueprint $table) {
            $table->foreignId('trade_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('specimen_transfers')->whereNull('trade_id')->delete();

        Schema::table('specimen_transfers', function (Blueprint $table) {
            $table->foreignId('trade_id')->nullable(false)->change();
        });
    }
};
