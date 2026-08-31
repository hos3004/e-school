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
        Schema::create('registration_questions', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->jsonb('question');
            $table->string('type', 16)->default('text');
            $table->jsonb('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['organization_id', 'is_active']);

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE registration_questions
            ADD CONSTRAINT registration_questions_type_check
            CHECK (type IN ('text', 'textarea', 'select', 'number'))
            SQL);

        // لقطة إجابات أسئلة التقييم داخل الطلب نفسه، حتى لا يمس تعديل الأسئلة
        // أو حذفها لاحقًا ما أُجيب عليه قبل ذلك.
        Schema::table('registration_applications', function (Blueprint $table): void {
            $table->jsonb('evaluation_answers')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('registration_applications', function (Blueprint $table): void {
            $table->dropColumn('evaluation_answers');
        });
        Schema::dropIfExists('registration_questions');
    }
};
