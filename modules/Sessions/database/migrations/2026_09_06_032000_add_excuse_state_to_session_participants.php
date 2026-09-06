<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_participants', function (Blueprint $table): void {
            $table->timestampTz('excused_at')->nullable()->after('attended_seconds');
            $table->char('excused_by', 26)->nullable()->after('excused_at');
            $table->text('excuse_reason')->nullable()->after('excused_by');

            $table->foreign('excused_by')->references('id')->on('users')->nullOnDelete();
            $table->index('excused_at');
        });
    }

    public function down(): void
    {
        Schema::table('session_participants', function (Blueprint $table): void {
            $table->dropForeign(['excused_by']);
            $table->dropIndex(['excused_at']);
            $table->dropColumn(['excused_at', 'excused_by', 'excuse_reason']);
        });
    }
};
