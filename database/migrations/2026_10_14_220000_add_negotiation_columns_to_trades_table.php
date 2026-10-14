<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->foreignId('counter_of_id')->nullable()->after('to_user_id')->constrained('trades')->nullOnDelete();
            $table->string('cancel_reason')->nullable()->after('status');
            $table->timestamp('expires_at')->nullable()->after('message');
            $table->timestamp('proposer_seen_at')->nullable()->after('responded_at');
            $table->index(['to_user_id', 'status']);
            $table->index(['from_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropIndex(['to_user_id', 'status']);
            $table->dropIndex(['from_user_id', 'status']);
            $table->dropConstrainedForeignId('counter_of_id');
            $table->dropColumn(['cancel_reason', 'expires_at', 'proposer_seen_at']);
        });
    }
};
