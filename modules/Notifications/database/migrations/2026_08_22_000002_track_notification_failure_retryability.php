<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_outbox', function (Blueprint $table): void {
            $table->boolean('last_error_retryable')->nullable()->after('last_error');
        });

        Schema::table('notification_delivery_attempts', function (Blueprint $table): void {
            $table->boolean('retryable')->nullable()->after('succeeded');
        });
    }

    public function down(): void
    {
        Schema::table('notification_delivery_attempts', function (Blueprint $table): void {
            $table->dropColumn('retryable');
        });

        Schema::table('notification_outbox', function (Blueprint $table): void {
            $table->dropColumn('last_error_retryable');
        });
    }
};
