<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إقفال البرنامج والكورس — إخراج من الواجهة مع بقاء كامل البيانات.
 *
 * الإقفال ليس حذفًا: `deleted_at` تبقى لسلة المهملات، و`closed_at` تعني
 * «انتهى وأُرشِف». لذلك عمود مستقل بدل إعادة استخدام الحذف الناعم — الحذف
 * الناعم يخفي الصف عن كل استعلام تلقائيًا عبر global scope، فتعود حصيلة
 * المؤرشَف أصفارًا، وهي بالضبط ما لا يجوز فقده عند الأرشفة.
 *
 * `closure_summary` لقطة مجمَّدة وقت الإقفال: الأعداد المحسوبة لحظيًا تتغير
 * تحت المستخدم كلما تحرك ما تحتها (طالب يُؤرشَف، كورس يُنقَل)، واللقطة لا تتغير.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['programs', 'courses'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->timestampTz('closed_at')->nullable();
                $blueprint->char('closed_by', 26)->nullable();
                $blueprint->text('closure_reason')->nullable();
                $blueprint->jsonb('closure_summary')->nullable();

                $blueprint->index(
                    ['organization_id', 'closed_at'],
                    $table.'_organization_id_closed_at_index',
                );
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropIndex($table.'_organization_id_closed_at_index');
                $blueprint->dropColumn(['closed_at', 'closed_by', 'closure_reason', 'closure_summary']);
            });
        }
    }
};
