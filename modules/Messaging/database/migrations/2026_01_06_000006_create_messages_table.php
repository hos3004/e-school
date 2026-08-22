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
        Schema::create('messages', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('conversation_id', 26);
            $table->char('user_id', 26);
            $table->text('body');
            $table->jsonb('attachments');
            $table->boolean('is_flagged');
            $table->text('flagged_reason')->nullable();
            $table->char('moderated_by', 26)->nullable();
            $table->timestampTz('moderated_at')->nullable();
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('edited_at')->nullable();
            $table->softDeletesTz();

            $table->index('organization_id', 'messages_organization_id_index');
            $table->index('user_id', 'messages_user_id_index');
            $table->index('moderated_by', 'messages_moderated_by_index');

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->restrictOnDelete();
            $table->foreign('conversation_id')
                ->references('id')
                ->on('conversations')
                ->restrictOnDelete();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
            $table->foreign('moderated_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        DB::statement(
            'CREATE INDEX messages_conversation_created_at_index '
            .'ON messages (conversation_id, created_at DESC)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
