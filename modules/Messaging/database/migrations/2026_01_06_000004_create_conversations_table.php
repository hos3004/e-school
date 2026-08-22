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
        Schema::create('conversations', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->string('subject');
            $table->string('type', 32);
            $table->boolean('is_moderated')->default(true);
            $table->string('related_type')->nullable();
            $table->char('related_id', 26)->nullable();
            $table->char('created_by', 26);
            $table->timestampTz('last_message_at')->nullable();
            $table->timestampTz('created_at')->nullable();
            $table->softDeletesTz();

            $table->index('organization_id', 'conversations_organization_id_index');
            $table->index(
                ['related_type', 'related_id'],
                'conversations_related_type_related_id_index'
            );
            $table->index('created_by', 'conversations_created_by_index');
            $table->index('last_message_at', 'conversations_last_message_at_index');

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->restrictOnDelete();
            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
