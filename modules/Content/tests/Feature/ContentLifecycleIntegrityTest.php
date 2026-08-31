<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Audit\Domain\Models\AuditLog;
use Modules\Content\Application\Actions\DeleteCourseMaterialAction;
use Modules\Content\Application\Actions\TransitionMaterialStatusAction;
use Modules\Content\Application\Actions\UpdateCourseMaterialAction;
use Modules\Content\Application\Actions\UploadCourseMaterialAction;
use Modules\Content\Domain\Enums\MaterialStatus;
use Modules\Content\Domain\Enums\MaterialType;
use Modules\Content\Domain\Models\CourseMaterial;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class);

it('preserves a complete audited revision history across create update publish and archive', function (): void {
    $organization = Organization::factory()->create();
    $actor = User::factory()->inOrganization((string) $organization->id)->create();
    $program = Program::factory()->create(['organization_id' => (string) $organization->id]);
    $level = Level::factory()->create(['program_id' => (string) $program->id]);
    $course = Course::factory()->create([
        'organization_id' => (string) $organization->id,
        'level_id' => (string) $level->id,
        'code' => 'CRS-CONTENT-01',
    ]);

    $this->actingAs($actor);
    $material = app(UploadCourseMaterialAction::class)->execute(
        organizationId: (string) $organization->id,
        data: [
            'course_id' => (string) $course->id,
            'title' => ['ar' => 'مقدمة المنهج', 'en' => 'Course Introduction'],
            'description' => ['ar' => 'المرجع الأول للطالب'],
            'type' => MaterialType::Link->value,
            'external_url' => 'https://example.test/content/introduction',
            'display_order' => 10,
        ],
        reason: 'إضافة مقدمة المنهج المعتمدة',
        actorId: (string) $actor->id,
    );

    expect($material->organization_id)->toBe((string) $organization->id)
        ->and($material->status)->toBe(MaterialStatus::Draft)
        ->and($material->revision)->toBe(1)
        ->and($material->versions()->count())->toBe(1)
        ->and($material->isCurrentlyVisible())->toBeFalse();

    $material = app(UpdateCourseMaterialAction::class)->execute(
        material: $material,
        data: [
            'title' => ['ar' => 'مقدمة المنهج المحدثة', 'en' => 'Updated Introduction'],
            'display_order' => 1,
        ],
        reason: 'ضبط العنوان والترتيب بعد مراجعة المنهج',
        actorId: (string) $actor->id,
    );
    $material = app(TransitionMaterialStatusAction::class)->execute(
        $material,
        MaterialStatus::Published,
        'اعتماد الإصدار للنشر إلى الطلاب',
        (string) $actor->id,
    );

    expect($material->revision)->toBe(3)
        ->and($material->versions()->count())->toBe(3)
        ->and($material->isCurrentlyVisible())->toBeTrue();

    app(DeleteCourseMaterialAction::class)->execute(
        $material,
        'أرشفة المادة بعد استبدالها بإصدار منهجي آخر',
        (string) $actor->id,
    );

    expect(CourseMaterial::withTrashed()->findOrFail($material->id)->trashed())->toBeTrue();

    foreach ([
        'content.material_created',
        'content.material_updated',
        'content.material_published',
        'content.material_archived',
    ] as $action) {
        expect(AuditLog::query()->where('action', $action)->exists())->toBeTrue("Missing audit {$action}");
    }

    expect(DB::table('course_material_versions')->where('material_id', $material->id)->count())->toBe(3);
});

it('rejects attaching content to a course from another organization', function (): void {
    $mine = Organization::factory()->create();
    $other = Organization::factory()->create();
    $actor = User::factory()->inOrganization((string) $mine->id)->create();
    $program = Program::factory()->create(['organization_id' => (string) $other->id]);
    $level = Level::factory()->create(['program_id' => (string) $program->id]);
    $foreignCourse = Course::factory()->create([
        'organization_id' => (string) $other->id,
        'level_id' => (string) $level->id,
    ]);

    $this->actingAs($actor);

    expect(fn () => app(UploadCourseMaterialAction::class)->execute(
        organizationId: (string) $mine->id,
        data: [
            'course_id' => (string) $foreignCourse->id,
            'title' => ['ar' => 'مادة عابرة للمؤسسات'],
            'type' => MaterialType::Link->value,
            'external_url' => 'https://example.test/blocked',
        ],
        reason: 'اختبار عزل المؤسسة',
        actorId: (string) $actor->id,
    ))->toThrow(BusinessRuleViolation::class);

    expect(CourseMaterial::query()->count())->toBe(0);
});

it('requires valid visibility and publishing state transitions', function (): void {
    $organization = Organization::factory()->create();
    $actor = User::factory()->inOrganization((string) $organization->id)->create();
    $program = Program::factory()->create(['organization_id' => (string) $organization->id]);
    $level = Level::factory()->create(['program_id' => (string) $program->id]);
    $course = Course::factory()->create([
        'organization_id' => (string) $organization->id,
        'level_id' => (string) $level->id,
    ]);

    $this->actingAs($actor);
    expect(fn () => app(UploadCourseMaterialAction::class)->execute(
        organizationId: (string) $organization->id,
        data: [
            'course_id' => (string) $course->id,
            'title' => ['ar' => 'نافذة غير صحيحة'],
            'type' => MaterialType::Link->value,
            'external_url' => 'https://example.test/window',
            'visible_from' => '2026-08-25 10:00:00',
            'visible_to' => '2026-08-25 09:00:00',
        ],
        reason: 'اختبار نافذة الظهور',
        actorId: (string) $actor->id,
    ))->toThrow(BusinessRuleViolation::class);
});

it('rolls the publishing schema down and reapplies it cleanly', function (): void {
    $migration = require base_path('modules/Content/database/migrations/2026_08_24_170000_expand_course_materials_for_publishing.php');

    $migration->down();
    expect(Schema::hasTable('course_material_versions'))->toBeFalse()
        ->and(Schema::hasColumn('course_materials', 'organization_id'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('course_material_versions'))->toBeTrue()
        ->and(Schema::hasColumn('course_materials', 'organization_id'))->toBeTrue()
        ->and(Schema::hasColumn('course_materials', 'revision'))->toBeTrue();
});
