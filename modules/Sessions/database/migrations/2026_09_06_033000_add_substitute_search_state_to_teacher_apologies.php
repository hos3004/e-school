<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_apologies', function (Blueprint $table): void {
            $table->timestampTz('substitute_search_started_at')->nullable();
            $table->timestampTz('last_substitute_search_at')->nullable();
            $table->jsonb('substitute_candidate_ids')->nullable();
            $table->unsignedInteger('substitute_candidate_count')->default(0);
            $table->index(['status', 'last_substitute_search_at'], 'teacher_apologies_search_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_apologies', function (Blueprint $table): void {
            $table->dropIndex('teacher_apologies_search_due_idx');
            $table->dropColumn([
                'substitute_search_started_at',
                'last_substitute_search_at',
                'substitute_candidate_ids',
                'substitute_candidate_count',
            ]);
        });
    }
};
