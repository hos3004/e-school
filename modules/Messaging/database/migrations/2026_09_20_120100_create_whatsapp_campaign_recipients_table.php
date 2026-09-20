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
        Schema::create('whatsapp_campaign_recipients', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('campaign_id', 26);
            $table->char('organization_id', 26);

            $table->string('name')->nullable();

            // الرقم كما كتبه المرسِل يبقى كما هو: هو ما يبحث عنه حين يصحّح رقمًا مرفوضًا.
            $table->string('phone_input', 64);
            $table->string('phone', 32)->nullable();

            $table->string('status', 32);
            $table->string('failure_reason')->nullable();
            $table->string('external_message_id')->nullable();

            $table->timestampTz('dispatch_after')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->integer('attempts')->default(0);

            $table->timestampsTz();

            $table->index(['campaign_id', 'status'], 'whatsapp_campaign_recipients_campaign_status_index');
            $table->index('organization_id', 'whatsapp_campaign_recipients_organization_id_index');
            $table->index('dispatch_after', 'whatsapp_campaign_recipients_dispatch_after_index');

            $table->foreign('campaign_id')
                ->references('id')
                ->on('whatsapp_campaigns')
                ->cascadeOnDelete();
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->restrictOnDelete();
        });

        /*
         * رقم واحد لا يتكرر داخل الحملة الواحدة: تكرار السطر في ملف مرفوع كان
         * سيعني رسالتين متطابقتين لنفس الشخص. الأرقام المرفوضة تبقى phone=NULL،
         * وPostgreSQL لا يعدّ NULL مكرَّرًا، فلا يمنع القيدُ تسجيلَ عدة أسطر خاطئة.
         */
        DB::statement(
            'ALTER TABLE whatsapp_campaign_recipients '
            .'ADD CONSTRAINT whatsapp_campaign_recipients_campaign_phone_unique UNIQUE (campaign_id, phone)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_campaign_recipients');
    }
};
