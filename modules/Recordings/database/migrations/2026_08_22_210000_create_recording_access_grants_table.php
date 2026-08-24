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
        Schema::create('recording_access_grants', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('recording_id', 26);
            $table->char('granted_to_user_id', 26)->nullable();
            $table->char('granted_to_group_id', 26)->nullable();
            $table->char('granted_by_user_id', 26);
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->text('reason');
            $table->timestampsTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('recording_id')->references('id')->on('recordings')->cascadeOnDelete();
            $table->foreign('granted_to_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('granted_to_group_id')->references('id')->on('groups')->restrictOnDelete();
            $table->foreign('granted_by_user_id')->references('id')->on('users')->restrictOnDelete();

            $table->index(['recording_id', 'granted_to_user_id']);
            $table->index(['recording_id', 'granted_to_group_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE recording_access_grants
            ADD CONSTRAINT recording_access_grants_exactly_one_target
            CHECK ((granted_to_user_id IS NULL) <> (granted_to_group_id IS NULL))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('recording_access_grants');
    }
};
