<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجل استبدال المعلمين — أثر دائم لكل تغيير.
 *
 * الاستبدال ليس تعديلًا على حقل معلم الحصة. الحصة تحتفظ دائمًا بالمعلم
 * الأصلي والمعلم الفعلي، وهذا الجدول يحفظ من غيّر ومتى ولماذا، حتى بعد
 * استبدالات متتالية على نفس الحصة.
 *
 * على هذا السجل تُبنى: صلاحية دخول الفصل، واحتساب المستحقات
 * (البديل بأجره وخصم حصة من الأساسي)، وبروفايل المعلم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_substitutions', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('session_id', 26);

            // من كان مسندًا للحصة قبل هذا التغيير، ومن أصبح مسندًا بعده.
            $table->char('original_teacher_id', 26);
            $table->char('substitute_teacher_id', 26);

            $table->text('reason');

            // هل كان المعلم البديل مستوفيًا لشروط الترشيح وقت الإسناد؟
            $table->boolean('was_qualified')->default(true);
            $table->boolean('was_available')->default(true);

            // تجاوز إداري: إسناد معلم غير مؤهل أو غير متاح بصلاحية خاصة.
            $table->boolean('is_override')->default(false);
            $table->text('override_reason')->nullable();

            $table->char('assigned_by', 26);
            $table->timestampTz('assigned_at');
            $table->timestampsTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('session_id')->references('id')->on('sessions')->cascadeOnDelete();
            $table->foreign('original_teacher_id')->references('id')->on('staff_profiles')->restrictOnDelete();
            $table->foreign('substitute_teacher_id')->references('id')->on('staff_profiles')->restrictOnDelete();
            $table->foreign('assigned_by')->references('id')->on('users')->restrictOnDelete();

            $table->index(['session_id', 'assigned_at']);
            $table->index('substitute_teacher_id');
            $table->index('original_teacher_id');
        });

        // المعلم لا يُستبدل بنفسه.
        Schema::getConnection()->statement(
            'ALTER TABLE session_substitutions
             ADD CONSTRAINT session_substitutions_distinct_teachers_check
             CHECK (original_teacher_id <> substitute_teacher_id)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('session_substitutions');
    }
};
