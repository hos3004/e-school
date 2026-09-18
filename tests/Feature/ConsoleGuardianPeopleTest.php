<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\Guardians\Domain\Enums\ContactChannel;
use Modules\Guardians\Domain\Events\GuardianLinkedToStudent;
use Modules\Guardians\Domain\Events\GuardianProfileCreated;
use Modules\Guardians\Domain\Events\GuardianUnlinkedFromStudent;
use Modules\Guardians\Domain\Models\GuardianLink;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Modules\Identity\Domain\Events\UserRegistered;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Models\Organization;
use Modules\Students\Domain\Models\StudentProfile;
use Tests\TestCase;

final class ConsoleGuardianPeopleTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $permissions = [
        'admin.panel.access', 'guardian.view', 'guardian.link', 'student.view.any',
        'enrollment.create', 'contact.pii.view',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['console.enabled' => true]);
        $this->withoutVite();
        Http::preventStrayRequests();
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
        $this->seed([GeographySeeder::class, AccessControlSeeder::class]);
        foreach ($this->permissions as $ability) {
            Gate::define($ability, fn (): bool => in_array($ability, $this->permissions, true));
        }
        Event::fake([
            UserRegistered::class, GuardianProfileCreated::class,
            GuardianLinkedToStudent::class, GuardianUnlinkedFromStudent::class,
        ]);
    }

    public function test_guardian_creation_with_initial_link_assigns_code_role_and_link_atomically(): void
    {
        [$organization, $actor, $student] = $this->context();

        $response = $this->actingAs($actor)->post('/manage/guardians', [
            'account_mode' => 'new', 'full_name' => 'Guardian Console', 'email' => 'guardian.console@example.test',
            'username' => 'guardian.console', 'password' => 'G8!Guardian-Console#2026',
            'password_confirmation' => 'G8!Guardian-Console#2026', 'locale' => 'ar', 'timezone' => 'Africa/Cairo',
            'national_id_last4' => '1234', 'occupation' => 'teacher',
            'preferred_contact_channel' => ContactChannel::WhatsApp->value,
            'student_profile_id' => (string) $student->id, 'relationship' => 'mother',
            'is_primary' => true, 'can_act_for' => true,
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();
        $user = User::query()->where('username', 'guardian.console')->firstOrFail();
        $guardian = GuardianProfile::query()->where('user_id', $user->id)->firstOrFail();
        self::assertSame((string) $organization->id, (string) $guardian->organization_id);
        self::assertMatchesRegularExpression('/^W\d{3,}$/', $guardian->guardian_code);
        self::assertTrue($user->must_change_password);
        $link = GuardianLink::query()->where('guardian_profile_id', $guardian->id)->firstOrFail();
        self::assertSame((string) $student->id, $link->student_profile_id);
        self::assertTrue($link->is_primary);
        $response->assertRedirect(route('console.guardians.show', ['profile' => $guardian->id]));
    }

    public function test_guardian_hub_lists_linked_student_and_respects_pii_visibility(): void
    {
        [$organization, $actor, $student] = $this->context();
        $guardianAccount = User::factory()->inOrganization((string) $organization->id)->create([
            'name' => 'Guardian Person', 'email' => 'guardian.person@example.test', 'phone' => '+201001234567',
        ]);
        $guardian = GuardianProfile::factory()->create([
            'organization_id' => $organization->id, 'user_id' => $guardianAccount->id,
        ]);
        GuardianLink::factory()->create([
            'guardian_profile_id' => $guardian->id, 'student_profile_id' => $student->id,
            'relationship' => 'mother', 'is_primary' => true,
        ]);

        $this->actingAs($actor)->get('/manage/guardians/'.$guardian->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Console/People/Show')
                ->where('kind', 'guardians')
                ->where('hub.students.0.student_profile_id', (string) $student->id)
                ->where('person.email', 'guardian.person@example.test')
                ->where('person.phone', '+201001234567'));

        Gate::define('contact.pii.view', static fn (): bool => false);
        $this->get('/manage/guardians/'.$guardian->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('person.email', null)
                ->where('person.phone', null));
    }

    public function test_guardian_directory_and_profile_never_expose_another_organization(): void
    {
        [$organization, $actor] = $this->context();
        $ownAccount = User::factory()->inOrganization((string) $organization->id)->create();
        $ownGuardian = GuardianProfile::factory()->create([
            'organization_id' => $organization->id, 'user_id' => $ownAccount->id,
        ]);
        $foreignOrganization = Organization::factory()->create();
        $foreignAccount = User::factory()->inOrganization((string) $foreignOrganization->id)->create();
        $foreignGuardian = GuardianProfile::factory()->create([
            'organization_id' => $foreignOrganization->id, 'user_id' => $foreignAccount->id,
        ]);

        $this->actingAs($actor)->get('/manage/guardians')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Console/People/Index')->has('people.data', 1)
            ->where('people.data.0.id', (string) $ownGuardian->id));
        $this->get('/manage/guardians/'.$foreignGuardian->id)->assertNotFound();
        $this->get('/manage/guardians/'.$foreignGuardian->id.'/edit')->assertNotFound();
    }

    public function test_linking_and_unlinking_a_student_from_the_guardian_profile_requires_a_reason(): void
    {
        [$organization, $actor, $student] = $this->context();
        $guardianAccount = User::factory()->inOrganization((string) $organization->id)->create();
        $guardian = GuardianProfile::factory()->create([
            'organization_id' => $organization->id, 'user_id' => $guardianAccount->id,
        ]);

        $this->actingAs($actor)->post('/manage/guardians/'.$guardian->id.'/links', [
            'student_profile_id' => (string) $student->id, 'relationship' => 'father',
            'is_primary' => true, 'can_act_for' => false, 'reason' => 'إثبات صلة القرابة',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $link = GuardianLink::query()->where('guardian_profile_id', $guardian->id)->firstOrFail();
        self::assertSame((string) $student->id, $link->student_profile_id);

        $this->post('/manage/guardians/'.$guardian->id.'/links', [
            'student_profile_id' => (string) $student->id, 'relationship' => 'father', 'reason' => '',
        ])->assertSessionHasErrors('reason');

        $this->delete('/manage/guardians/'.$guardian->id.'/links/'.$link->id, [
            'reason' => 'طلب ولي الأمر إزالة الربط',
        ])->assertSessionHasNoErrors()->assertRedirect();
        self::assertSoftDeleted('guardian_links', ['id' => $link->id]);
    }

    public function test_unlinking_a_link_belonging_to_another_guardian_is_rejected(): void
    {
        [$organization, $actor, $student] = $this->context();
        $guardianAccount = User::factory()->inOrganization((string) $organization->id)->create();
        $guardian = GuardianProfile::factory()->create([
            'organization_id' => $organization->id, 'user_id' => $guardianAccount->id,
        ]);
        $otherAccount = User::factory()->inOrganization((string) $organization->id)->create();
        $otherGuardian = GuardianProfile::factory()->create([
            'organization_id' => $organization->id, 'user_id' => $otherAccount->id,
        ]);
        $link = GuardianLink::factory()->create([
            'guardian_profile_id' => $otherGuardian->id, 'student_profile_id' => $student->id,
        ]);

        $this->actingAs($actor)
            ->delete('/manage/guardians/'.$guardian->id.'/links/'.$link->id, ['reason' => 'محاولة غير صحيحة'])
            ->assertNotFound();
        self::assertDatabaseHas('guardian_links', ['id' => $link->id, 'deleted_at' => null]);
    }

    public function test_guardian_profile_edit_updates_occupation_without_touching_identity_without_ability(): void
    {
        [$organization, $actor] = $this->context();
        $this->permissions = [
            'admin.panel.access', 'guardian.view', 'guardian.link', 'student.view.any', 'contact.pii.view',
        ];
        $account = User::factory()->inOrganization((string) $organization->id)->create([
            'name' => 'Protected Guardian', 'phone' => '+201001234567', 'timezone' => 'Africa/Cairo',
        ]);
        $guardian = GuardianProfile::factory()->create([
            'organization_id' => $organization->id, 'user_id' => $account->id, 'occupation' => 'old-job',
        ]);

        $this->actingAs($actor)->get('/manage/guardians/'.$guardian->id.'/edit')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canUpdateAccount', false));

        $this->put('/manage/guardians/'.$guardian->id, [
            'full_name' => 'Unauthorized name', 'phone' => '+201009999999', 'timezone' => 'Asia/Tokyo',
            'occupation' => 'new-job',
        ])->assertForbidden();
        self::assertSame('Protected Guardian', $account->fresh()->name);

        $this->put('/manage/guardians/'.$guardian->id, ['occupation' => 'new-job'])
            ->assertSessionHasNoErrors()->assertRedirect();
        self::assertSame('new-job', $guardian->fresh()->occupation);
    }

    /**
     * تعديل الهوية وحدها (بلا أي حقل خاص بملف الوصي) لا يجب أن يفشل بخطأ
     * "لا يوجد ما يُحدَّث" ولا يسقط تحديث الاسم/التوقيت الذي نجح فعلًا.
     */
    public function test_updating_only_identity_fields_on_a_guardian_does_not_roll_back(): void
    {
        [$organization, $actor] = $this->context();
        $account = User::factory()->inOrganization((string) $organization->id)->create([
            'name' => 'Old Name', 'timezone' => 'Africa/Cairo',
        ]);
        $guardian = GuardianProfile::factory()->create([
            'organization_id' => $organization->id, 'user_id' => $account->id, 'occupation' => 'unchanged-job',
        ]);
        Gate::define('identity.users.update', static fn (): bool => true);

        $this->actingAs($actor)->put('/manage/guardians/'.$guardian->id, [
            'full_name' => 'New Name', 'timezone' => 'Europe/London',
        ])->assertSessionHasNoErrors()->assertRedirect();

        self::assertSame('New Name', $account->fresh()->name);
        self::assertSame('Europe/London', $account->fresh()->timezone);
        self::assertSame('unchanged-job', $guardian->fresh()->occupation);
    }

    /**
     * ولي الأمر ليس له جدول حصص شخصي؛ اختيار "schedule" كنوع رسالة يُرفض من
     * الخادم مباشرة، لا من إخفاء الخيار في الواجهة وحده.
     */
    public function test_schedule_message_kind_is_rejected_server_side_for_a_guardian(): void
    {
        [$organization, $actor] = $this->context();
        $account = User::factory()->inOrganization((string) $organization->id)->create();
        $guardian = GuardianProfile::factory()->create([
            'organization_id' => $organization->id, 'user_id' => $account->id,
        ]);
        Gate::define('notifications.outbox.create', static fn (): bool => true);

        $this->actingAs($actor)->post('/manage/messages/guardians/'.$guardian->id, [
            'kind' => 'schedule', 'channel' => 'in_app', 'reason' => 'محاولة غير صحيحة',
            'request_id' => (string) \Illuminate\Support\Str::ulid(),
        ])->assertSessionHasErrors('kind');
    }

    /** @return array{Organization, User, StudentProfile} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->inOrganization((string) $organization->id)->create();
        $studentAccount = User::factory()->inOrganization((string) $organization->id)->create(['name' => 'Linked Student']);
        $student = StudentProfile::factory()->create([
            'organization_id' => $organization->id, 'user_id' => $studentAccount->id,
        ]);

        return [$organization, $actor, $student];
    }
}
