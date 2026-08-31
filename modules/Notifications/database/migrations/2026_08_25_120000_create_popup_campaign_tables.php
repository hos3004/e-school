<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('popup_campaigns', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->string('internal_name');
            $table->string('type', 32);
            $table->string('status', 16)->default('draft')->index();
            $table->smallInteger('priority')->default(5);

            // محتوى مترجم jsonb: {'ar': ..., 'en': ..., 'fr': ...} — العربية إلزامية.
            $table->jsonb('title');
            $table->jsonb('body');

            // قائمة جمهور بقيم Enum — ليست strings عشوائية.
            $table->jsonb('audiences');

            $table->string('placement', 32)->index();
            $table->string('page_key', 64)->nullable();

            $table->string('frequency', 24)->default('once');
            $table->boolean('is_dismissible')->default(true);
            $table->boolean('requires_acknowledgement')->default(false);
            $table->jsonb('acknowledgement_label')->nullable();

            $table->jsonb('action_label')->nullable();
            $table->string('action_type', 16)->nullable(); // null|internal_page|external_url
            $table->string('action_target', 500)->nullable();

            $table->timestampTz('starts_at')->index();
            $table->timestampTz('ends_at')->nullable()->index();
            $table->timestampTz('published_at')->nullable();
            $table->char('published_by', 26)->nullable();
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('published_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            // الاستعلام الأكثرة: مؤسسة + حالة + نافذة العرض + أولوية.
            $table->index(['organization_id', 'status', 'placement', 'starts_at', 'priority']);
        });

        Schema::create('popup_campaign_user_state', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('campaign_id', 26);
            $table->char('user_id', 26);
            $table->char('organization_id', 26);

            $table->timestampTz('first_seen_at')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->unsignedInteger('impressions_count')->default(0);
            $table->timestampTz('dismissed_at')->nullable();
            $table->timestampTz('acknowledged_at')->nullable();
            $table->timestampTz('clicked_at')->nullable();

            // علامة جلسة الدخول الأخيرة التي شُوهدت فيها الحملة (OncePerLogin).
            $table->string('login_marker', 64)->nullable();

            $table->timestampsTz();

            $table->unique(['campaign_id', 'user_id']);
            $table->index(['user_id', 'organization_id']);

            $table->foreign('campaign_id')->references('id')->on('popup_campaigns')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popup_campaign_user_state');
        Schema::dropIfExists('popup_campaigns');
    }
};
