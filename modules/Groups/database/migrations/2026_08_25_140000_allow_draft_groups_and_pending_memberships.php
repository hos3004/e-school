<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * يجعل المجموعة «قيد التخطيط» قابلة للإنشاء بالحد الأدنى من البيانات.
 *
 * السعة وتاريخ البداية يصيران مؤجّلين ما دامت الحالة `planning` فقط؛ قيد
 * `groups_activation_completeness_check` يمنع أي حالة أخرى من الوجود بلا
 * هاتين القيمتين، فلا تتسرب مجموعة نشطة ناقصة عبر أي مسار — حتى الكتابة
 * المباشرة في القاعدة.
 *
 * ويضيف كذلك قيد تحقق على حالة الانتساب يشمل `pending`، وهي الحالة التي يولد
 * بها انتساب الطالب داخل مجموعة قيد التخطيط.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table): void {
            $table->unsignedInteger('capacity')->nullable()->change();
            $table->date('starts_on')->nullable()->change();
        });

        // السعة تبقى ضمن المدى المسموح متى وُجدت، وتُقبل فارغة أثناء التخطيط.
        DB::statement('ALTER TABLE groups DROP CONSTRAINT IF EXISTS groups_capacity_range_check');
        DB::statement(<<<'SQL'
            ALTER TABLE groups
            ADD CONSTRAINT groups_capacity_range_check
            CHECK (capacity IS NULL OR capacity BETWEEN 1 AND 25)
            SQL);

        // لا مجموعة نشطة أو مُختمة بلا سعة وتاريخ بداية — التأجيل حكر على التخطيط.
        DB::statement('ALTER TABLE groups DROP CONSTRAINT IF EXISTS groups_activation_completeness_check');
        DB::statement(<<<'SQL'
            ALTER TABLE groups
            ADD CONSTRAINT groups_activation_completeness_check
            CHECK (
                status = 'planning'
                OR (capacity IS NOT NULL AND starts_on IS NOT NULL)
            )
            SQL);

        DB::statement('ALTER TABLE group_memberships DROP CONSTRAINT IF EXISTS group_memberships_status_check');
        DB::statement(<<<'SQL'
            ALTER TABLE group_memberships
            ADD CONSTRAINT group_memberships_status_check
            CHECK (status IN ('pending', 'active', 'left'))
            SQL);
    }

    public function down(): void
    {
        // الانتساب المعلّق لا مقابل له في المخطط القديم؛ يُرقّى إلى نشط بدل أن
        // يُحذف — القاعدة الحاكمة: لا حذف لبيانات بشرية، تغيير حالة فقط.
        DB::table('group_memberships')
            ->where('status', 'pending')
            ->update(['status' => 'active']);

        DB::statement('ALTER TABLE group_memberships DROP CONSTRAINT IF EXISTS group_memberships_status_check');
        DB::statement('ALTER TABLE groups DROP CONSTRAINT IF EXISTS groups_activation_completeness_check');

        // إعادة العمودين إلى NOT NULL تتطلب قيمة لكل صف مؤجَّل: السعة القصوى
        // المعلَنة في الإعدادات، وتاريخ اليوم — قيم صريحة موثقة لا عشوائية.
        $maximumCapacity = (int) config('groups.capacity.maximum', 25);

        DB::table('groups')->whereNull('capacity')->update(['capacity' => $maximumCapacity]);
        DB::statement('UPDATE groups SET starts_on = CURRENT_DATE WHERE starts_on IS NULL');

        Schema::table('groups', function (Blueprint $table): void {
            $table->unsignedInteger('capacity')->nullable(false)->change();
            $table->date('starts_on')->nullable(false)->change();
        });

        DB::statement('ALTER TABLE groups DROP CONSTRAINT IF EXISTS groups_capacity_range_check');
        DB::statement('ALTER TABLE groups ADD CONSTRAINT groups_capacity_range_check CHECK (capacity BETWEEN 1 AND 25)');
    }
};
