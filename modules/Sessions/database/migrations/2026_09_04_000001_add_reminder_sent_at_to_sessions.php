<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->timestampTz('reminder_sent_at')->nullable()->after('scheduled_end');
            $table->index(['reminder_sent_at', 'scheduled_start'], 'sessions_reminder_dispatch_index');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropIndex('sessions_reminder_dispatch_index');
            $table->dropColumn('reminder_sent_at');
        });
    }
};
