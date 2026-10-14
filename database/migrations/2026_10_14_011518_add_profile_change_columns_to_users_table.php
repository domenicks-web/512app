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
            $table->timestamp('nickname_changed_at')->nullable()->after('tag');
            $table->timestamp('birthdate_corrected_at')->nullable()->after('birthdate');
            $table->string('pending_email')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nickname_changed_at', 'birthdate_corrected_at', 'pending_email']);
        });
    }
};
