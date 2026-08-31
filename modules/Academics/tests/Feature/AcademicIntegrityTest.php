<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Academics\Application\Actions\ArchiveCourseAction;
use Modules\Academics\Application\Actions\ArchiveProgramAction;
use Modules\Academics\Application\Actions\CreateCourseAction;
use Modules\Academics\Application\Actions\CreateLevelAction;
use Modules\Academics\Application\Actions\CreateProgramAction;
use Modules\Academics\Application\Actions\CreateProgramCategoryAction;
use Modules\Academics\Application\Actions\UpdateCourseAction;
use Modules\Academics\Application\Actions\UpdateProgramAction;
use Modules\Academics\Domain\Models\Program;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Gate::define('program.manage', static fn (): bool => true);
    Gate::define('course.manage', static fn (): bool => true);
});

it('derives program organization from the actor and blocks cross-tenant records', function (): void {
    $mine = Organization::factory()->create();
    $other = Organization::factory()->create();
    $operator = User::factory()->inOrganization((string) $mine->id)->create();

    $response = $this->actingAs($operator)->postJson('/api/academics/programs', [
        'organization_id' => (string) $other->id,
        'code' => 'PRG-TENANT-01',
        'name' => ['ar' => 'برنامج مؤسسي'],
        'default_session_minutes' => 60,
        'currency' => 'EGP',
        'program_type' => 'ongoing',
        'target_gender' => 'all',
        'reason' => 'اختبار اشتقاق المؤسسة',
    ])->assertCreated();

    $created = Program::query()->findOrFail((string) $response->json('data.id'));
    expect((string) $created->organization_id)->toBe((string) $mine->id);

    $foreign = Program::factory()->create(['organization_id' => (string) $other->id]);
    $this->putJson("/api/academics/programs/{$foreign->id}", [
        'duration_weeks' => 20,
        'reason' => 'محاولة تعديل برنامج أجنبي',
    ])->assertForbidden();
});

it('audits the complete academic catalog lifecycle with written reasons', function (): void {
    $organization = Organization::factory()->create();
    $actor = User::factory()->inOrganization((string) $organization->id)->create();

    $program = app(CreateProgramAction::class)->execute([
        'organization_id' => (string) $organization->id,
        'code' => 'PRG-AUDIT-01',
        'name' => ['ar' => 'برنامج التدقيق'],
        'default_session_minutes' => 60,
        'currency' => 'EGP',
        'program_type' => 'ongoing',
        'target_gender' => 'all',
        'eligibility' => ['countries' => [], 'regions' => [], 'manual_approval_required' => true],
    ], (string) $actor->id, 'إطلاق البرنامج الأكاديمي');

    $level = app(CreateLevelAction::class)->execute([
        'organization_id' => (string) $organization->id,
        'program_id' => (string) $program->id,
        'code' => 'L1',
        'name' => ['ar' => 'المستوى الأول'],
        'sort_order' => 1,
    ], (string) $actor->id, 'بناء تسلسل البرنامج');

    $category = app(CreateProgramCategoryAction::class)->execute([
        'organization_id' => (string) $organization->id,
        'program_id' => (string) $program->id,
        'code' => 'CORE',
        'name' => ['ar' => 'المقررات الأساسية'],
        'is_active' => true,
    ], (string) $actor->id, 'تصنيف المقررات الأساسية');

    $course = app(CreateCourseAction::class)->execute([
        'organization_id' => (string) $organization->id,
        'level_id' => (string) $level->id,
        'code' => 'CRS-AUDIT-01',
        'name' => ['ar' => 'كورس التدقيق'],
        'session_mode' => 'both',
        'category_ids' => [(string) $category->id],
    ], (string) $actor->id, 'إضافة الكورس للمنهج');

    app(UpdateProgramAction::class)->execute($program, ['duration_weeks' => 12], (string) $actor->id, 'تحديد مدة الخطة');
    app(UpdateCourseAction::class)->execute($course, ['total_sessions' => 16], (string) $actor->id, 'اعتماد عدد الحصص');
    app(ArchiveCourseAction::class)->execute($course->refresh(), 'إغلاق نسخة الكورس', (string) $actor->id);
    app(ArchiveProgramAction::class)->execute($program->refresh(), 'إغلاق نسخة البرنامج', (string) $actor->id);

    foreach ([
        'academics.program_created', 'academics.level_created', 'academics.category_created',
        'academics.course_created', 'academics.program_updated', 'academics.course_updated',
        'academics.course_archived', 'academics.program_archived',
    ] as $action) {
        expect(DB::table('audit_log')->where('action', $action)->exists())->toBeTrue("Missing audit action {$action}");
    }
});
