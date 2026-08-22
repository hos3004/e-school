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
        Schema::create('groups', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->string('code')->unique();
            $table->jsonb('name');
            $table->unsignedInteger('capacity');
            $table->string('timezone');
            $table->string('status');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('organization_id');
            $table->index(['organization_id', 'status']);

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE groups ADD CONSTRAINT groups_capacity_range_check CHECK (capacity BETWEEN 1 AND 25)');
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
