<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_courses', function (Blueprint $table): void {
            $table->timestampTz('revoked_at')->nullable()->after('notes');
            $table->char('revoked_by', 26)->nullable()->after('revoked_at');
            $table->text('revocation_reason')->nullable()->after('revoked_by');

            $table->index('revoked_at');

            $table->foreign('revoked_by')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teacher_courses', function (Blueprint $table): void {
            $table->dropForeign(['revoked_by']);
            $table->dropIndex(['revoked_at']);
            $table->dropColumn(['revoked_at', 'revoked_by', 'revocation_reason']);
        });
    }
};
