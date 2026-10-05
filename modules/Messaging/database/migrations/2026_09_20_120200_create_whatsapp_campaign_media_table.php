<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_campaign_media', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('campaign_id', 26);
            $table->char('organization_id', 26);

            $table->string('disk', 64);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 128);
            $table->bigInteger('size_bytes');

            // ترتيب الإرسال: المرفقات تصل بالترتيب الذي رتّبه المرسِل.
            $table->integer('position')->default(0);

            $table->timestampTz('deleted_file_at')->nullable();
            $table->timestampsTz();

            $table->index(['campaign_id', 'position'], 'whatsapp_campaign_media_campaign_position_index');
            $table->index('organization_id', 'whatsapp_campaign_media_organization_id_index');

            $table->foreign('campaign_id')
                ->references('id')
                ->on('whatsapp_campaigns')
                ->cascadeOnDelete();
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_campaign_media');
    }
};
