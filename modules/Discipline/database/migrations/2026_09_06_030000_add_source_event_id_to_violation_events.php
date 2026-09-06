<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('violation_events', function (Blueprint $table): void {
            $table->char('source_event_id', 26)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('violation_events', function (Blueprint $table): void {
            $table->dropUnique(['source_event_id']);
            $table->dropColumn('source_event_id');
        });
    }
};
