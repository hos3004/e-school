<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * محتوى البوت القابل للتحرير: نصوصه وقواعد حدوده.
 *
 * الجدولان معًا لأنهما وجها عملة واحدة: القاعدة تقرر ماذا يحدث، والنص يقرر
 * بأي كلمات يحدث. تحرير أحدهما بلا الآخر يترك فجوة (قاعدة إرشاد بلا نص إرشاد).
 *
 * كلاهما يتبع نمط notification_templates: organization_id يقبل NULL ليعني
 * «الصف العام المشحون مع النظام»، وصف المؤسسة يغطّيه. وis_active بدل الحذف
 * حتى يمكن تعطيل مدخلة ثم إعادتها دون فقد صياغتها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_bot_entries', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->nullable()->index();

            // instruction | knowledge | reply
            $table->string('kind', 16);
            $table->string('key', 128);
            $table->string('locale', 16);

            $table->string('title', 200)->nullable();
            $table->text('body');

            /*
             * الفئات التي تُعرض لها هذه المدخلة. قائمة فارغة = للجميع.
             * قيم Enum لا نصوص حرة.
             */
            $table->jsonb('audiences');

            /*
             * الموضوع الذي تخدمه هذه المدخلة. لصفوف reply هو المفتاح الذي
             * تشير إليه القاعدة؛ ولصفوف knowledge هو ما يرشّحها للسياق.
             */
            $table->string('topic', 64)->nullable()->index();

            $table->smallInteger('priority')->default(5);
            $table->boolean('is_active')->default(true);

            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            /*
             * NULLS NOT DISTINCT: في PostgreSQL تُعدّ القيم الفارغة مختلفة داخل
             * الفهرس الفريد، فلولا هذا لما منع الفهرس صفّين عامّين للمفتاح نفسه،
             * ولصار الفائز بينهما رهين ترتيب الصفوف على القرص.
             */
            $table->unique(['organization_id', 'kind', 'key', 'locale'], 'support_bot_entries_scope_unique')
                ->nullsNotDistinct();
            $table->index(['kind', 'locale', 'is_active', 'priority'], 'support_bot_entries_lookup_idx');
        });

        Schema::create('support_bot_rules', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->nullable()->index();

            $table->string('topic', 64);
            $table->string('audience', 32);

            // allow | guide | deny
            $table->string('mode', 16);

            /*
             * مفتاح صف reply المستخدَم في وضعَي guide وdeny. لا مفتاح خارجي
             * عليه عمدًا: المفتاح نصّي ويُحَل مع تغطية صف المؤسسة للصف العام،
             * وFK إلى صف بعينه كان سيكسر هذه التغطية.
             */
            $table->string('reply_key', 128)->nullable();

            $table->boolean('is_active')->default(true);
            $table->char('updated_by', 26)->nullable();

            $table->timestampsTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['organization_id', 'topic', 'audience'], 'support_bot_rules_scope_unique')
                ->nullsNotDistinct();
            $table->index(['topic', 'audience', 'is_active'], 'support_bot_rules_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_bot_rules');
        Schema::dropIfExists('support_bot_entries');
    }
};
