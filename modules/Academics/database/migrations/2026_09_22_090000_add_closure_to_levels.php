<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إقفال المستوى — إخراج من الواجهة مع بقاء كامل البيانات.
 *
 * نفس عقد إقفال البرنامج والكورس والمجموعة. الفارق أن `levels` بلا
 * `organization_id` — المستوى يرث مؤسسته من برنامجه — فالفهرس على
 * `(program_id, closed_at)` لأن كل قراءة للمستويات تبدأ من برنامجها.
 *
 * الجدول أيضًا بلا `deleted_at`: المستوى لم يكن يُحذف ناعمًا أصلًا، وهذه
 * الهجرة لا تضيف حذفًا — تضيف إقفالًا، وهما مفهومان مختلفان عمدًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('levels', function (Blueprint $table): void {
            $table->timestampTz('closed_at')->nullable();
            $table->char('closed_by', 26)->nullable();
            $table->text('closure_reason')->nullable();
            $table->jsonb('closure_summary')->nullable();

            $table->index(['program_id', 'closed_at'], 'levels_program_id_closed_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('levels', function (Blueprint $table): void {
            $table->dropIndex('levels_program_id_closed_at_index');
            $table->dropColumn(['closed_at', 'closed_by', 'closure_reason', 'closure_summary']);
        });
    }
};
