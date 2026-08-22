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
        Schema::create('whatsapp_inbound', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->string('from_phone', 32);
            $table->string('message_id')->unique('whatsapp_inbound_message_id_unique');
            $table->text('body');
            $table->jsonb('media')->nullable();
            $table->timestampTz('received_at');
            $table->char('matched_user_id', 26)->nullable();
            $table->char('handled_by', 26)->nullable();
            $table->timestampTz('handled_at')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index('organization_id', 'whatsapp_inbound_organization_id_index');
            $table->index('matched_user_id', 'whatsapp_inbound_matched_user_id_index');
            $table->index('handled_by', 'whatsapp_inbound_handled_by_index');

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->restrictOnDelete();
            $table->foreign('matched_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('handled_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_inbound');
    }
};
