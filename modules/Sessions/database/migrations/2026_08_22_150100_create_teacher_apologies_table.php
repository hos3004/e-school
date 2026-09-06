<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اعتذار المعلم عن حصة — قاعدة العميل 2026-08-22 (client-answers §ي · §ك).
 *
 * القاعدة الحاكمة التي يقوم عليها هذا الجدول:
 * **اعتذار المعلم لا يُلغي الحصة.** يُعتمد فورًا ويبدأ البحث عن بديل،
 * وتبقى الحصة قائمة بحالتها. لذلك لا يوجد هنا أي عمود يُلغي حصة.
 *
 * لماذا جدول مستقل ولم نستخدم postponement_requests الموجود؟
 * التأجيل يطلبه **الطالب** ويُنتج حصة تلافي بموعد جديد. الاعتذار يقدّمه
 * **المعلم** ولا يغيّر الموعد إطلاقًا — يغيّر من يُنفِّذ. مساران مختلفان
 * في الفاعل والنتيجة وسُلَّم المتابعة، ودمجهما كان سيخلط قاعدتَي عمل.
 *
 * سُلَّم المتابعة (تحذير عند الثانية، تصعيد عند الثالثة) يُحتسب على نافذة
 * **متحركة** من config('discipline.teacher.counter_window_days') — لا شهر
 * ميلادي. ولذلك نخزّن decided_at كطابع زمني دقيق ولا نخزّن مفتاح دلو شهري:
 * الدلو الثابت يعطي نتيجة خاطئة مع النافذة المتحركة.
 *
 * ولا يوجد هنا أي أثر لعقوبة: لا تعليق ولا إنهاء ولا تغيير حالة معلم.
 * الإشراف يتلقى إشعارًا ومتابعة تشغيلية ولا يوافق على الاعتذار.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_apologies', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('session_id', 26);
            $table->char('staff_profile_id', 26);

            $table->string('status');

            // السبب إلزامي على مستوى قاعدة البيانات أيضًا، لا في الـFormRequest وحده.
            $table->text('reason');

            $table->timestampTz('submitted_at');

            /*
             * هل قُدّم الاعتذار متأخرًا عن المهلة المعلنة؟
             * يُحسب وقت التقديم من config('scheduling.apology.min_notice_minutes')
             * ويُجمَّد هنا: تغيير الإعداد لاحقًا يجب ألا يعيد تصنيف اعتذار قديم.
             */
            $table->boolean('is_late_notice')->default(false);
            $table->integer('notice_minutes')->nullable();

            $table->char('decided_by', 26)->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_reason')->nullable();

            /*
             * الاستبدال الناتج عن هذا الاعتذار — يُملأ عند إسناد البديل.
             * قد يبقى فارغًا: اعتذار معتمد لم يُوجد له بديل بعد، وهذا هو
             * ما يُصعَّد للإدارة لا ما يُلغي الحصة.
             */
            $table->char('substitution_id', 26)->nullable();

            /*
             * مرتبة هذا الاعتذار داخل النافذة المتحركة وقت اعتماده (1 · 2 · 3 …).
             * تُجمَّد لحظة الاعتماد حتى يبقى سجل التصعيد قابلًا للتفسير لاحقًا
             * حتى لو تغيّر طول النافذة في الإعدادات.
             */
            $table->unsignedSmallInteger('occurrence_in_window')->nullable();
            $table->unsignedSmallInteger('window_days')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('session_id')->references('id')->on('sessions')->cascadeOnDelete();
            $table->foreign('staff_profile_id')->references('id')->on('staff_profiles')->restrictOnDelete();
            $table->foreign('decided_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('substitution_id')->references('id')->on('session_substitutions')->nullOnDelete();

            $table->index(['staff_profile_id', 'submitted_at']);
            $table->index(['organization_id', 'status']);
            $table->index('session_id');
        });

        // اعتذار معتمد لا يكون بلا قرار، والمرفوض لا يكون بلا سبب رفض.
        Schema::getConnection()->statement(
            "ALTER TABLE teacher_apologies
             ADD CONSTRAINT teacher_apologies_decision_complete_check
             CHECK (
                 status IN ('submitted', 'withdrawn')
                 OR (decided_by IS NOT NULL AND decided_at IS NOT NULL)
             )",
        );

        // معلم واحد لا يقدّم اعتذارين معلّقين لنفس الحصة.
        Schema::getConnection()->statement(
            "CREATE UNIQUE INDEX teacher_apologies_one_open_per_session
             ON teacher_apologies (session_id, staff_profile_id)
             WHERE status = 'submitted' AND deleted_at IS NULL",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_apologies');
    }
};
