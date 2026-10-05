<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_campaigns', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('created_by', 26)->nullable();
            $table->string('name');
            $table->text('body');
            $table->string('status', 32);
            $table->text('reason');

            // حدّا المهلة بين رسالة وأخرى كما اختارهما المرسِل لهذه الحملة.
            $table->integer('delay_min_seconds');
            $table->integer('delay_max_seconds');

            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);

            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();

            // موعد إتلاف المرفقات؛ يُحسب عند انتهاء الحملة لا عند إنشائها.
            $table->timestampTz('media_expires_at')->nullable();
            $table->timestampTz('media_pruned_at')->nullable();

            $table->timestampsTz();

            $table->index('organization_id', 'whatsapp_campaigns_organization_id_index');
            $table->index('status', 'whatsapp_campaigns_status_index');
            $table->index('media_expires_at', 'whatsapp_campaigns_media_expires_at_index');

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->restrictOnDelete();
            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_campaigns');
    }
};
