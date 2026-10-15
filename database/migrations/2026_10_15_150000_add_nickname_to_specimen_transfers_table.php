<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** O apelido que o dono anterior (`from_user_id`) tinha dado. */
    public function up(): void
    {
        Schema::table('specimen_transfers', function (Blueprint $table): void {
            $table->string('nickname', 16)->nullable()->after('trade_id');
        });
    }

    public function down(): void
    {
        Schema::table('specimen_transfers', function (Blueprint $table): void {
            $table->dropColumn('nickname');
        });
    }
};
