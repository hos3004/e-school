<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * المعلم الأصلي مقابل المعلم الفعلي — قاعدة العميل 2026-08-22.
 *
 * «الحصة يجب ألا تفقد المدرس الأصلي.» عند الاستبدال يتغيّر من يُنفِّذ الحصة،
 * لكن من كان مسندًا لها أصلًا يبقى مسجَّلًا إلى الأبد.
 *
 * النموذج المعتمد هنا — ولماذا لم نضف عمودًا ثالثًا:
 *
 *   original_teacher_id  = من أُسند للحصة عند إنشائها. **لا يتغيّر أبدًا.**
 *   staff_profile_id     = المعلم **الفعلي** الذي ينفّذ الحصة الآن.
 *
 * إضافة عمود `actual_teacher_id` منفصل كانت ستكرّر `staff_profile_id` وتفتح باب
 * التعارض بينهما. الأهم أن قيد منع الحجز المزدوج (sessions_no_teacher_double_booking)
 * مبنيّ على `staff_profile_id`، وهذا **هو الصحيح**: التعارض يخصّ من يقف في الفصل
 * فعلًا لا من كان مسندًا على الورق. تغيير العمود المحمي بالقيد كان سيكسر الحماية.
 *
 * لذلك: `staff_profile_id` هو المعلم الفعلي، ويكشفه الموديل باسم `actual_teacher_id`
 * صراحةً حتى لا يبقى المعنى ضمنيًا.
 *
 * العمود القديم `substitute_for_staff_id` كان يحمل نصف المعنى فقط (من استُبدل)،
 * ولا يصمد أمام استبدالات متتالية. نُبقيه للتوافق ونعلّمه مهجورًا؛ السجل الكامل
 * لكل استبدال في جدول session_substitutions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->char('original_teacher_id', 26)->nullable()->after('staff_profile_id');
        });

        // الحصص القائمة لم تُستبدل: الأصلي هو الفعلي نفسه.
        DB::table('sessions')
            ->whereNull('original_teacher_id')
            ->update(['original_teacher_id' => DB::raw('staff_profile_id')]);

        // الحصص التي سبق استبدالها بالنموذج القديم: الأصلي هو من استُبدل عنه.
        DB::table('sessions')
            ->whereNotNull('substitute_for_staff_id')
            ->update(['original_teacher_id' => DB::raw('substitute_for_staff_id')]);

        Schema::table('sessions', function (Blueprint $table): void {
            $table->char('original_teacher_id', 26)->nullable(false)->change();

            $table->foreign('original_teacher_id')
                ->references('id')->on('staff_profiles')
                ->restrictOnDelete();

            $table->index(['original_teacher_id', 'scheduled_start']);
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropForeign(['original_teacher_id']);
            $table->dropIndex(['original_teacher_id', 'scheduled_start']);
            $table->dropColumn('original_teacher_id');
        });
    }
};
