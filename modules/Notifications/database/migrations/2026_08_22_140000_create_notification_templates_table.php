<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->nullable()->index();
            $table->string('event_key');
            $table->string('channel', 32);
            $table->string('locale', 16);
            $table->text('subject')->nullable();
            $table->text('body');
            $table->string('provider_template_name')->nullable();
            $table->jsonb('parameters');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->restrictOnDelete();

            $table->unique(
                ['organization_id', 'event_key', 'channel', 'locale'],
                'notification_templates_scope_unique',
            );
            $table->index(
                ['event_key', 'channel', 'locale', 'is_active'],
                'notification_templates_lookup_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
