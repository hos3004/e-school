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
        Schema::create('audit_log', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->nullable();
            $table->char('actor_id', 26)->nullable();
            $table->string('actor_type', 32)->default('user');
            $table->char('acting_for_user_id', 26)->nullable();
            $table->string('action', 128);
            $table->string('auditable_type', 191);
            $table->char('auditable_id', 26)->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->char('correlation_id', 26)->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index(['organization_id', 'auditable_type', 'auditable_id']);
            $table->index('actor_id');
            $table->index('correlation_id');
        });

        DB::statement('ALTER TABLE audit_log ALTER COLUMN ip_address TYPE inet USING ip_address::inet');
        DB::statement('CREATE INDEX audit_log_created_at_desc_index ON audit_log (created_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
