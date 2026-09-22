<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * أرشيف محادثات البوت.
 *
 * **لماذا جداول مستقلة ولا نعيد استخدام جداول المراسلات؟** سياسة المحادثات في
 * موديول Messaging تمنح من يملك message.moderate قراءة كل محادثات المؤسسة.
 * لو عاشت محادثات البوت هناك لصار كل مشرف قادرًا على قراءة ما كتبه كل طالب
 * للبوت — وهو تسريب خصوصية لا ميزة. كذلك messages.user_id مفتاح خارجي غير قابل
 * للإفراغ، فكان البوت سيحتاج حساب مستخدم وهميًا ليُنسب إليه الكلام.
 *
 * **ذاكرة البوت تنتهي بانتهاء الجلسة، والأرشيف يبقى.** الفصل مقصود: البوت لا
 * يستدعي محادثة الأمس في سياقه إطلاقًا، لكن الإدارة تحتاج أن تراجع شكوى وأن
 * تعرف أكثر ما يُسأل عنه. لذلك closed_at يقفل الجلسة ولا يحذف شيئًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_bot_conversations', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('user_id', 26);

            $table->string('audience', 32)->index();
            $table->string('locale', 16);

            $table->timestampTz('started_at');
            $table->timestampTz('last_message_at')->nullable();
            $table->timestampTz('closed_at')->nullable();

            $table->unsignedInteger('message_count')->default(0);

            /*
             * عدد المرات التي أوقف فيها الحارس إجابة. مؤشر مباشر على فجوة في
             * قاعدة المعرفة أو على مستخدم يحاول الوصول لما لا يخصه — كلاهما
             * يستحق أن يظهر في التقرير الدوري.
             */
            $table->unsignedInteger('blocked_count')->default(0);

            $table->timestampsTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();

            $table->index(['organization_id', 'user_id', 'last_message_at'], 'support_bot_conversations_owner_idx');
            $table->index(['organization_id', 'started_at'], 'support_bot_conversations_period_idx');
        });

        Schema::create('support_bot_messages', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('conversation_id', 26);
            $table->char('organization_id', 26);

            // user | bot
            $table->string('role', 16);
            $table->text('body');

            /*
             * قرار الحارس محفوظ مع الرسالة نفسها لا محسوبًا لاحقًا: القواعد
             * قابلة للتحرير، فإعادة حسابها بعد شهر كانت ستعطي تفسيرًا مختلفًا
             * لما حدث فعلًا وقت المحادثة.
             */
            $table->string('topic', 64)->nullable()->index();
            $table->string('mode', 16)->nullable();
            $table->boolean('was_generated')->default(false);

            $table->string('model', 64)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('latency_milliseconds')->nullable();
            $table->string('failure_reason', 64)->nullable();

            $table->char('correlation_id', 26)->nullable()->index();

            $table->timestampTz('created_at');

            $table->foreign('conversation_id')->references('id')->on('support_bot_conversations')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
        });

        // ترتيب العرض والتقليم: الأحدث أولًا داخل المحادثة.
        DB::statement('CREATE INDEX support_bot_messages_thread_idx ON support_bot_messages (conversation_id, created_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('support_bot_messages');
        Schema::dropIfExists('support_bot_conversations');
    }
};
