<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_participants', function (Blueprint $table): void {
            $table->timestampTz('current_joined_at')->nullable();
            $table->unsignedInteger('attended_seconds')->default(0);
        });

        DB::table('session_participants')->update([
            'attended_seconds' => DB::raw('attended_minutes * 60'),
        ]);
    }

    public function down(): void
    {
        Schema::table('session_participants', function (Blueprint $table): void {
            $table->dropColumn(['current_joined_at', 'attended_seconds']);
        });
    }
};
