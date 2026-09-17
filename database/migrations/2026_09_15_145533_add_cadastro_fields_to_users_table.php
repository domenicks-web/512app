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
            $table->string('nickname')->nullable()->after('id');
            $table->string('tag', 4)->nullable()->after('nickname');
            $table->date('birthdate')->nullable()->after('email_verified_at');
            $table->unsignedSmallInteger('avatar_species_id')->nullable()->after('birthdate');
            $table->boolean('is_admin')->default(false)->after('avatar_species_id');

            $table->unique(['nickname', 'tag']);
            $table->foreign('avatar_species_id')->references('id')->on('species');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['avatar_species_id']);
            $table->dropUnique(['nickname', 'tag']);
            $table->dropColumn(['nickname', 'tag', 'birthdate', 'avatar_species_id', 'is_admin']);
        });
    }
};
