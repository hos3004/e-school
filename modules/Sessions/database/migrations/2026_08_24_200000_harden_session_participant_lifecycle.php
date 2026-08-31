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
            $table->timestampTz('revoked_at')->nullable()->after('invited_at');
            $table->char('revoked_by', 26)->nullable()->after('revoked_at');
            $table->text('revocation_reason')->nullable()->after('revoked_by');
            $table->softDeletesTz();

            $table->foreign('revoked_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['student_profile_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::table('session_participants', function (Blueprint $table): void {
            $table->dropIndex(['student_profile_id', 'revoked_at']);
            $table->dropForeign(['revoked_by']);
            $table->dropColumn(['revoked_at', 'revoked_by', 'revocation_reason', 'deleted_at']);
        });
    }
};
