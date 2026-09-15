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
            $table->string('nickname')->after('id');
            $table->string('tag', 4)->after('nickname');
            $table->date('birthdate')->after('email_verified_at');
            $table->string('avatar_seed')->after('birthdate');
            $table->boolean('is_admin')->default(false)->after('avatar_seed');

            $table->unique(['nickname', 'tag']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nickname', 'tag']);
            $table->dropColumn(['nickname', 'tag', 'birthdate', 'avatar_seed', 'is_admin']);
        });
    }
};
