<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_attempts', function (Blueprint $table): void {
            $table->text('feedback')->nullable();
            $table->softDeletesTz();
            $table->unique(
                ['assessment_id', 'student_profile_id', 'attempt_number'],
                'assessment_attempts_number_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('assessment_attempts', function (Blueprint $table): void {
            $table->dropUnique('assessment_attempts_number_unique');
            $table->dropColumn(['feedback', 'deleted_at']);
        });
    }
};
