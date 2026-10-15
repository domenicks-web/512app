<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Professor Carvalho: a conta do sistema que guarda os espécimes
 * libertados no banimento. Senha aleatória que ninguém sabe; o login e o
 * middleware recusam `is_system` de qualquer jeito.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->insert([
            'email' => 'sistema@512app.local',
            'password' => Hash::make(Str::random(64)),
            'nickname' => 'Professor Carvalho',
            'tag' => '0000',
            'client_seed' => Str::random(24),
            'is_system' => true,
            'invite_quota' => 0,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('users')->where('is_system', true)->delete();
    }
};
