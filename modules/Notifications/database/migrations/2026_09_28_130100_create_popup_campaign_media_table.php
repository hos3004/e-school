<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ملفات الميديا المرفقة بحملة منبثقة: صورة/فيديو/صوت/ملف قابل للتنزيل.
 * القرص والمسار دائمًا من الرفع الفعلي (StorePopupCampaignMediaController)،
 * لا من مدخلات العميل أبدًا — نفس نمط whatsapp_campaign_media.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('popup_campaign_media', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('campaign_id', 26);
            $table->string('kind', 16);
            $table->string('disk', 32);
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->boolean('has_sound')->nullable();
            $table->smallInteger('position')->default(0);

            $table->timestampsTz();

            $table->foreign('campaign_id')->references('id')->on('popup_campaigns')->cascadeOnDelete();
            $table->index(['campaign_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popup_campaign_media');
    }
};
