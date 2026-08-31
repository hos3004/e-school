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
        Schema::table('course_materials', function (Blueprint $table): void {
            $table->char('organization_id', 26)->nullable()->after('id');
            $table->jsonb('description')->nullable()->after('title');
            $table->string('status')->default('draft')->after('type');
            $table->unsignedInteger('display_order')->default(0)->after('status');
            $table->unsignedInteger('revision')->default(1)->after('display_order');
            $table->timestampTz('published_at')->nullable()->after('visible_to');
            $table->char('published_by', 26)->nullable()->after('published_at');
        });

        DB::statement(
            'UPDATE course_materials SET organization_id = courses.organization_id '.
            'FROM courses WHERE course_materials.course_id = courses.id',
        );
        DB::statement('ALTER TABLE course_materials ALTER COLUMN organization_id SET NOT NULL');

        Schema::table('course_materials', function (Blueprint $table): void {
            $table->index(['organization_id', 'status'], 'course_materials_org_status_index');
            $table->index(['course_id', 'display_order'], 'course_materials_course_order_index');
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
        });

        Schema::create('course_material_versions', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('material_id', 26);
            $table->unsignedInteger('revision');
            $table->jsonb('snapshot');
            $table->char('changed_by', 26)->nullable();
            $table->text('reason');
            $table->timestampTz('created_at');

            $table->unique(['material_id', 'revision']);
            $table->index('material_id');
            $table->foreign('material_id')->references('id')->on('course_materials')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_material_versions');

        Schema::table('course_materials', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropIndex('course_materials_org_status_index');
            $table->dropIndex('course_materials_course_order_index');
            $table->dropColumn([
                'organization_id',
                'description',
                'status',
                'display_order',
                'revision',
                'published_at',
                'published_by',
            ]);
        });
    }
};
