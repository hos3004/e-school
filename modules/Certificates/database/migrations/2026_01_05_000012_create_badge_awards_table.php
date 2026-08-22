<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_awards', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('badge_id', 26);
            $table->char('user_id', 26);
            $table->char('awarded_by', 26)->nullable();
            $table->text('reason')->nullable();
            $table->timestampTz('awarded_at');
            $table->timestampsTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('badge_id')->references('id')->on('badges')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('awarded_by')->references('id')->on('users')->restrictOnDelete();

            $table->index(['organization_id', 'user_id']);
            $table->index('badge_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_awards');
    }
};
