<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table): void {
            $table->integer('raw_score')->nullable()->after('attachments');
            $table->integer('penalty_points')->default(0)->after('raw_score');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table): void {
            $table->dropColumn(['raw_score', 'penalty_points']);
        });
    }
};
