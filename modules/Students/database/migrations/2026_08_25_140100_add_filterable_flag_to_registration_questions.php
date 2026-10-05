<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * قائمة بيضاء صريحة لما يجوز الفلترة به من إجابات نموذج التسجيل.
 *
 * إجابات الأسئلة الديناميكية بيانات شخصية؛ عرضها جميعًا كفلاتر تلقائيًا يحوّل
 * شاشة التسجيلات إلى أداة تنقيب. لذلك لا يصير السؤال قابلًا للفلترة إلا
 * بتفعيل صريح من الإدارة، ولا يُسمح بذلك إلا للأنواع المنضبطة (`select`
 * و`number`) — النص الحر يبقى خارج الفلترة دائمًا.
 *
 * ويضيف فهرس GIN على لقطة الإجابات لأن الفلترة تستخدم عامل الاحتواء `@>`
 * في PostgreSQL، وهو العامل الذي يستفيد من هذا النوع من الفهارس.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_questions', function (Blueprint $table): void {
            $table->boolean('is_filterable')->default(false)->after('is_active');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE registration_questions
            ADD CONSTRAINT registration_questions_filterable_type_check
            CHECK (is_filterable = false OR type IN ('select', 'number'))
            SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX registration_applications_evaluation_answers_gin
            ON registration_applications
            USING gin (evaluation_answers jsonb_path_ops)
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS registration_applications_evaluation_answers_gin');
        DB::statement('ALTER TABLE registration_questions DROP CONSTRAINT IF EXISTS registration_questions_filterable_type_check');

        Schema::table('registration_questions', function (Blueprint $table): void {
            $table->dropColumn('is_filterable');
        });
    }
};
