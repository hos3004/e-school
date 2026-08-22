<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_participants', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('conversation_id', 26);
            $table->char('user_id', 26);
            $table->string('role', 32);
            $table->timestampTz('joined_at');
            $table->timestampTz('last_read_at')->nullable();
            $table->timestampTz('muted_until')->nullable();

            $table->index('organization_id', 'conversation_participants_organization_id_index');
            $table->index('conversation_id', 'conversation_participants_conversation_id_index');
            $table->index('user_id', 'conversation_participants_user_id_index');

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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');
    }
};
