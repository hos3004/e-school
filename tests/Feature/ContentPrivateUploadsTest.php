<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Modules\Content\Domain\Enums\MaterialStatus;
use Modules\Content\Domain\Enums\MaterialType;
use Modules\Content\Domain\Models\CourseMaterial;
use Modules\Content\Presentation\Filament\Resources\CourseMaterialResource\Pages\CreateCourseMaterial;
use Modules\Content\Presentation\Filament\Resources\CourseMaterialResource\Pages\EditCourseMaterial;
use Modules\Identity\Domain\Models\User;
use Tests\TestCase;

final class ContentPrivateUploadsTest extends TestCase
{
    use CreatesSessionParticipant;
    use RefreshDatabase;

    public function test_new_file_is_private_and_editing_legacy_public_metadata_preserves_its_disk_and_path(): void
    {
        $this->withoutVite();
        Gate::before(static fn (): bool => true);
        Filament::setCurrentPanel('admin');
        Storage::fake('local');
        Storage::fake('public');
        config(['content.uploads.disk' => 'local']);
        $participant = DB::table('session_participants')->find($this->createSessionParticipant());
        $session = DB::table('sessions')->find($participant->session_id);
        $actor = User::query()->findOrFail(DB::table('staff_profiles')->where('id', $session->staff_profile_id)->value('user_id'));
        $this->actingAs($actor);

        Livewire::test(CreateCourseMaterial::class)->fillForm([
            'course_id' => $session->course_id, 'title' => ['ar' => 'مادة خاصة جديدة'],
            'description' => ['ar' => 'نسخة المراجعة'], 'type' => 'file', 'display_order' => 0,
            'path' => UploadedFile::fake()->create('lesson.pdf', 12, 'application/pdf'),
            'reason' => 'إضافة المادة بعد المراجعة',
        ])->call('create')->assertHasNoFormErrors();

        $new = CourseMaterial::query()->where('organization_id', $session->organization_id)->sole();
        $this->assertSame('local', $new->disk);
        Storage::disk('local')->assertExists($new->path);
        Storage::disk('public')->assertMissing($new->path);
        $this->assertSame('private', Storage::disk('local')->getVisibility($new->path));
        $this->assertSame(MaterialStatus::Draft, $new->status);

        $legacyPath = 'course-materials/legacy.pdf';
        Storage::disk('public')->put($legacyPath, 'legacy preserved content');
        $legacy = CourseMaterial::query()->create([
            'organization_id' => $session->organization_id, 'course_id' => $session->course_id,
            'title' => ['ar' => 'مادة قديمة', 'en' => 'Legacy title'], 'type' => MaterialType::File,
            'status' => MaterialStatus::Published, 'revision' => 1, 'disk' => 'public',
            'path' => $legacyPath, 'size_bytes' => 24, 'display_order' => 0,
        ]);
        Livewire::test(EditCourseMaterial::class, ['record' => $legacy->id])
            ->fillForm(['title.ar' => 'اسم محدث فقط', 'reason' => 'تحديث اسم المادة'])
            ->call('save')->assertHasNoFormErrors();
        $legacy->refresh();
        $this->assertSame('public', $legacy->disk);
        $this->assertSame($legacyPath, $legacy->path);
        $this->assertSame('Legacy title', $legacy->title['en']);
        Storage::disk('public')->assertExists($legacyPath);
        Storage::disk('local')->assertMissing($legacyPath);

        Livewire::test(EditCourseMaterial::class, ['record' => $legacy->id])
            ->set('data.path', [])
            ->fillForm(['path' => UploadedFile::fake()->create('replacement.pdf', 9, 'application/pdf'), 'reason' => 'رفع النسخة الجديدة'])
            ->call('save')->assertHasNoFormErrors();
        $legacy->refresh();
        $this->assertSame('local', $legacy->disk);
        $this->assertNotSame($legacyPath, $legacy->path);
        Storage::disk('local')->assertExists($legacy->path);
        $this->assertSame('private', Storage::disk('local')->getVisibility($legacy->path));
        Storage::disk('public')->assertExists($legacyPath);
        $this->assertDatabaseHas('course_material_versions', ['material_id' => $legacy->id, 'snapshot->path' => $legacyPath, 'snapshot->disk' => 'public']);
    }
}
