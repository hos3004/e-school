<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_forms', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->string('slug', 120)->unique();
            $table->jsonb('title');
            $table->jsonb('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['organization_id', 'is_active']);
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
        });

        Schema::table('registration_questions', function (Blueprint $table): void {
            $table->char('registration_form_id', 26)->nullable()->after('organization_id');
            $table->index(
                ['registration_form_id', 'is_active', 'sort_order'],
                'reg_questions_form_active_sort_idx',
            );
        });

        $organizationIds = DB::table('registration_questions')
            ->select('organization_id')
            ->distinct()
            ->pluck('organization_id');

        foreach ($organizationIds as $organizationId) {
            if (!is_string($organizationId) || $organizationId === '') {
                continue;
            }

            $formId = (string) Str::ulid();
            DB::table('registration_forms')->insert([
                'id' => $formId,
                'organization_id' => $organizationId,
                'slug' => 'legacy-'.mb_strtolower($organizationId),
                'title' => json_encode([
                    'ar' => 'نموذج التسجيل الأساسي',
                    'en' => 'Default registration form',
                    'fr' => 'Formulaire d’inscription principal',
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'description' => null,
                'is_active' => true,
                'created_at' => now()->utc(),
                'updated_at' => now()->utc(),
            ]);

            DB::table('registration_questions')
                ->where('organization_id', $organizationId)
                ->whereNull('registration_form_id')
                ->update(['registration_form_id' => $formId]);
        }

        Schema::table('registration_questions', function (Blueprint $table): void {
            $table->foreign('registration_form_id')->references('id')->on('registration_forms')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE registration_questions DROP CONSTRAINT registration_questions_type_check');
        DB::statement(<<<'SQL'
            ALTER TABLE registration_questions
            ADD CONSTRAINT registration_questions_type_check
            CHECK (type IN ('text', 'textarea', 'select', 'radio', 'checkbox', 'number'))
            SQL);

        DB::statement('ALTER TABLE registration_questions DROP CONSTRAINT registration_questions_filterable_type_check');
        DB::statement(<<<'SQL'
            ALTER TABLE registration_questions
            ADD CONSTRAINT registration_questions_filterable_type_check
            CHECK (is_filterable = false OR type IN ('select', 'radio', 'number'))
            SQL);

        Schema::table('registration_applications', function (Blueprint $table): void {
            $table->char('registration_form_id', 26)->nullable()->after('organization_id');
            $table->index(
                ['organization_id', 'registration_form_id', 'submitted_at'],
                'reg_apps_org_form_submitted_idx',
            );
            $table->foreign('registration_form_id')->references('id')->on('registration_forms')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registration_applications', function (Blueprint $table): void {
            $table->dropForeign(['registration_form_id']);
            $table->dropIndex('reg_apps_org_form_submitted_idx');
            $table->dropColumn('registration_form_id');
        });

        Schema::table('registration_questions', function (Blueprint $table): void {
            $table->dropForeign(['registration_form_id']);
            $table->dropIndex('reg_questions_form_active_sort_idx');
            $table->dropColumn('registration_form_id');
        });

        DB::statement('ALTER TABLE registration_questions DROP CONSTRAINT registration_questions_type_check');
        DB::statement(<<<'SQL'
            ALTER TABLE registration_questions
            ADD CONSTRAINT registration_questions_type_check
            CHECK (type IN ('text', 'textarea', 'select', 'number'))
            SQL);

        DB::statement('ALTER TABLE registration_questions DROP CONSTRAINT registration_questions_filterable_type_check');
        DB::statement(<<<'SQL'
            ALTER TABLE registration_questions
            ADD CONSTRAINT registration_questions_filterable_type_check
            CHECK (is_filterable = false OR type IN ('select', 'number'))
            SQL);

        Schema::dropIfExists('registration_forms');
    }
};
