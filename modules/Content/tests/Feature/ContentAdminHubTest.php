<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Content\Application\Actions\TransitionMaterialStatusAction;
use Modules\Content\Application\Actions\UploadCourseMaterialAction;
use Modules\Content\Domain\Enums\MaterialStatus;
use Modules\Content\Domain\Enums\MaterialType;
use Modules\Content\Presentation\Filament\Resources\CourseMaterialResource;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;

uses(RefreshDatabase::class);

it('renders a real content library and never exposes storage internals through api', function (): void {
    Gate::before(static fn (): bool => true);
    Filament::setCurrentPanel('admin');

    $organization = Organization::factory()->create();
    $actor = User::factory()->inOrganization((string) $organization->id)->create(['name' => 'مدير المحتوى']);
    $program = Program::factory()->create([
        'organization_id' => (string) $organization->id,
        'name' => ['ar' => 'برنامج التجويد', 'en' => 'Tajweed Program'],
    ]);
    $level = Level::factory()->create(['program_id' => (string) $program->id]);
    $course = Course::factory()->create([
        'organization_id' => (string) $organization->id,
        'level_id' => (string) $level->id,
        'code' => 'CRS-TAJWEED-1',
        'name' => ['ar' => 'أحكام النون الساكنة', 'en' => 'Noon Rules'],
    ]);

    $this->actingAs($actor);
    $material = app(UploadCourseMaterialAction::class)->execute(
        organizationId: (string) $organization->id,
        data: [
            'course_id' => (string) $course->id,
            'title' => ['ar' => 'شرح الإخفاء الحقيقي', 'en' => 'Ikhfa Lesson'],
            'type' => MaterialType::Link->value,
            'external_url' => 'https://example.test/tajweed/ikhfa',
        ],
        reason: 'إضافة الدرس الأول إلى مكتبة الكورس',
        actorId: (string) $actor->id,
    );
    $material = app(TransitionMaterialStatusAction::class)->execute(
        $material,
        MaterialStatus::Published,
        'نشر الدرس بعد المراجعة العلمية',
        (string) $actor->id,
    );

    $this->get(CourseMaterialResource::getUrl('create', panel: 'admin'))
        ->assertOk()
        ->assertSeeText(__('content::fields.academic_context'))
        ->assertDontSee('data.organization_id', false);

    $this->get(CourseMaterialResource::getUrl('view', ['record' => $material], panel: 'admin'))
        ->assertOk()
        ->assertSeeText('شرح الإخفاء الحقيقي')
        ->assertSeeText('أحكام النون الساكنة')
        ->assertSeeText('إضافة الدرس الأول إلى مكتبة الكورس')
        ->assertSeeText('نشر الدرس بعد المراجعة العلمية')
        ->assertSeeText(__('content::messages.version_history'));

    $this->actingAs($actor, 'sanctum')
        ->getJson('/api/content/materials/'.$material->id)
        ->assertOk()
        ->assertJsonMissingPath('data.disk')
        ->assertJsonMissingPath('data.path')
        ->assertJsonPath('data.source.external_url', 'https://example.test/tajweed/ikhfa');
});

it('hides another organization material from the content hub', function (): void {
    Gate::before(static fn (): bool => true);
    Filament::setCurrentPanel('admin');

    $mine = Organization::factory()->create();
    $other = Organization::factory()->create();
    $actor = User::factory()->inOrganization((string) $mine->id)->create();
    $program = Program::factory()->create(['organization_id' => (string) $other->id]);
    $level = Level::factory()->create(['program_id' => (string) $program->id]);
    $course = Course::factory()->create([
        'organization_id' => (string) $other->id,
        'level_id' => (string) $level->id,
    ]);
    $foreignActor = User::factory()->inOrganization((string) $other->id)->create();
    $material = app(UploadCourseMaterialAction::class)->execute(
        organizationId: (string) $other->id,
        data: [
            'course_id' => (string) $course->id,
            'title' => ['ar' => 'مادة المؤسسة الأخرى'],
            'type' => MaterialType::Link->value,
            'external_url' => 'https://example.test/foreign',
        ],
        reason: 'إنشاء مادة للمؤسسة الأخرى',
        actorId: (string) $foreignActor->id,
    );

    $this->actingAs($actor)
        ->get(CourseMaterialResource::getUrl('view', ['record' => $material], panel: 'admin'))
        ->assertNotFound();
});
