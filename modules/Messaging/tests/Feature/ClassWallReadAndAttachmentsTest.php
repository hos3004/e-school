<?php

declare(strict_types=1);

namespace Modules\Messaging\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Identity\Domain\Models\User;
use Shared\Testing\Fixtures;
use Tests\TestCase;

/**
 * القراءة (عرض المنشورات/التعليقات وصور المرفقات) كانت مفقودة كليًا قبل
 * هذا التغيير — الكتابة (نشر/تعليق) كانت موجودة ومحميّة، لكن لا شاشة ولا
 * endpoint كان يعرضها. هذه الحزمة تثبت أن العرض محمي بنفس تفويض الوصول
 * للصف، لا مفتوح للجميع.
 */
final class ClassWallReadAndAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Fixtures::flush();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_teacher_lists_posts_of_their_own_group_pinned_first(): void
    {
        $organizationId = Fixtures::organizationId();
        $teacher = User::factory()->inOrganization($organizationId)->create();
        $staffProfileId = Fixtures::staffProfileForUser($teacher->id);
        $groupId = $this->insertGroupWithTeacher($organizationId, $staffProfileId);
        $this->grantPermission($teacher, 'class_wall.post');

        $this->actingAs($teacher)->postJson('/api/wall/posts', [
            'group_id' => $groupId,
            'body' => 'منشور عادي',
        ])->assertCreated();

        $this->actingAs($teacher)->postJson('/api/wall/posts', [
            'group_id' => $groupId,
            'body' => 'منشور مثبّت',
            'is_pinned' => true,
        ])->assertCreated();

        $response = $this->actingAs($teacher)
            ->getJson("/api/wall/groups/{$groupId}/posts")
            ->assertOk();

        $posts = $response->json('data');
        self::assertCount(2, $posts);
        self::assertTrue($posts[0]['is_pinned']);
        self::assertSame($teacher->name, $posts[0]['author_name']);
    }

    public function test_teacher_outside_the_group_cannot_list_its_posts(): void
    {
        $organizationId = Fixtures::organizationId();
        $owner = User::factory()->inOrganization($organizationId)->create();
        $ownerStaffProfileId = Fixtures::staffProfileForUser($owner->id);
        $groupId = $this->insertGroupWithTeacher($organizationId, $ownerStaffProfileId);

        $stranger = User::factory()->inOrganization($organizationId)->create();
        Fixtures::staffProfileForUser($stranger->id);
        $this->grantPermission($stranger, 'class_wall.post');

        $this->actingAs($stranger)
            ->getJson("/api/wall/groups/{$groupId}/posts")
            ->assertForbidden();
    }

    public function test_a_student_in_the_group_can_now_view_comments(): void
    {
        $organizationId = Fixtures::organizationId();
        $teacher = User::factory()->inOrganization($organizationId)->create();
        $staffProfileId = Fixtures::staffProfileForUser($teacher->id);
        $groupId = $this->insertGroupWithTeacher($organizationId, $staffProfileId);
        $this->grantPermission($teacher, 'class_wall.post');

        $student = User::factory()->inOrganization($organizationId)->create();
        $studentProfileId = Fixtures::studentProfileForUser($student->id);
        $this->addStudentToGroup($groupId, $studentProfileId);
        $this->grantPermission($student, 'message.send');

        $post = $this->actingAs($teacher)->postJson('/api/wall/posts', [
            'group_id' => $groupId,
            'body' => 'واجب الأسبوع',
        ])->assertCreated();
        $postId = (string) $post->json('data.id');

        $this->actingAs($student)->postJson("/api/wall/posts/{$postId}/comments", [
            'body' => 'تمام يا أستاذ',
        ])->assertCreated();

        $comments = $this->actingAs($student)
            ->getJson("/api/wall/posts/{$postId}/comments")
            ->assertOk();

        $comments->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.author_name', $student->name);
    }

    public function test_post_with_image_returns_a_fetchable_attachment_url_guarded_by_class_access(): void
    {
        $organizationId = Fixtures::organizationId();
        $teacher = User::factory()->inOrganization($organizationId)->create();
        $staffProfileId = Fixtures::staffProfileForUser($teacher->id);
        $groupId = $this->insertGroupWithTeacher($organizationId, $staffProfileId);
        $this->grantPermission($teacher, 'class_wall.post');

        $image = UploadedFile::fake()->image('note.jpg', 200, 200)->size(50);

        $response = $this->actingAs($teacher)->post('/api/wall/posts', [
            'group_id' => $groupId,
            'body' => 'صورة السبورة',
            'image' => $image,
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $attachments = $response->json('data.attachments');
        self::assertCount(1, $attachments);
        $url = (string) $attachments[0]['url'];

        $this->actingAs($teacher)->get($url)->assertOk();

        $stranger = User::factory()->inOrganization($organizationId)->create();
        Fixtures::staffProfileForUser($stranger->id);
        $this->actingAs($stranger)->get($url)->assertForbidden();
    }

    /**
     * attachments كان عمودًا حرّ الشكل يقبل أي قيمة من العميل، وServeAttachment
     * الجديد كان سيثق بـdisk/path كما وردا — أي حقن {"disk":"r2","path":"..."}
     * كان يجعل هذا المسار يخدم ملفًا من قرص تعسفي. هذا الاختبار يثبت أن الحقل
     * مرفوض كليًا الآن قبل أن يصل لأي منطق تخزين.
     */
    public function test_client_supplied_attachments_field_is_rejected(): void
    {
        $organizationId = Fixtures::organizationId();
        $teacher = User::factory()->inOrganization($organizationId)->create();
        $staffProfileId = Fixtures::staffProfileForUser($teacher->id);
        $groupId = $this->insertGroupWithTeacher($organizationId, $staffProfileId);
        $this->grantPermission($teacher, 'class_wall.post');

        $this->actingAs($teacher)->postJson('/api/wall/posts', [
            'group_id' => $groupId,
            'body' => 'محاولة حقن مرفق',
            'attachments' => [['disk' => 'r2', 'path' => 'recordings/some-other-file.mp4']],
        ])->assertUnprocessable();
    }

    /**
     * حتى لو استقرّت قيمة disk/path مفبركة داخل عمود attachments بأي طريق آخر
     * مستقبلًا (سجلّ قديم، مسار كتابة لم نتوقعه)، ShowWallAttachmentController
     * لازم يتجاهل disk المخزّن تمامًا ويرفض أي path لا يبدأ بمجلد حائط هذه
     * المجموعة بالذات — لا يكفي فقط منع الحقن وقت الإنشاء.
     */
    public function test_attachment_endpoint_ignores_a_foreign_disk_and_path_even_if_stored(): void
    {
        $organizationId = Fixtures::organizationId();
        $teacher = User::factory()->inOrganization($organizationId)->create();
        $staffProfileId = Fixtures::staffProfileForUser($teacher->id);
        $groupId = $this->insertGroupWithTeacher($organizationId, $staffProfileId);
        $this->grantPermission($teacher, 'class_wall.post');

        $post = $this->actingAs($teacher)->postJson('/api/wall/posts', [
            'group_id' => $groupId,
            'body' => 'منشور عادي',
        ])->assertCreated();
        $postId = (string) $post->json('data.id');

        DB::table('class_wall_posts')->where('id', $postId)->update([
            'attachments' => json_encode([[
                'type' => 'image',
                'disk' => 'r2',
                'path' => 'recordings/private-session.mp4',
                'id' => (string) Str::ulid(),
            ]]),
        ]);

        $this->actingAs($teacher)
            ->get("/api/wall/posts/{$postId}/attachments/0")
            ->assertNotFound();
    }

    public function test_a_teacher_cannot_post_to_a_group_they_do_not_teach(): void
    {
        $organizationId = Fixtures::organizationId();
        $owner = User::factory()->inOrganization($organizationId)->create();
        $ownerStaffProfileId = Fixtures::staffProfileForUser($owner->id);
        $groupId = $this->insertGroupWithTeacher($organizationId, $ownerStaffProfileId);

        $stranger = User::factory()->inOrganization($organizationId)->create();
        Fixtures::staffProfileForUser($stranger->id);
        $this->grantPermission($stranger, 'class_wall.post');

        $this->actingAs($stranger)->postJson('/api/wall/posts', [
            'group_id' => $groupId,
            'body' => 'محاولة تجاوز',
        ])->assertUnprocessable();
    }

    private function insertGroupWithTeacher(string $organizationId, string $staffProfileId): string
    {
        $groupId = (string) Str::ulid();

        DB::table('groups')->insert([
            'id' => $groupId,
            'organization_id' => $organizationId,
            'code' => 'WALL-'.strtoupper(substr($groupId, -6)),
            'name' => json_encode(['ar' => 'مجموعة الحائط', 'en' => 'Wall Test Group'], JSON_UNESCAPED_UNICODE),
            'capacity' => 10,
            'timezone' => 'UTC',
            'status' => 'active',
            'starts_on' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $courseId = Fixtures::courseId();

        DB::table('group_teachers')->insert([
            'id' => (string) Str::ulid(),
            'group_id' => $groupId,
            'staff_profile_id' => $staffProfileId,
            'course_id' => $courseId,
            'role' => 'lead',
            'assigned_from' => now()->toDateString(),
            'created_at' => now(),
        ]);

        return $groupId;
    }

    private function addStudentToGroup(string $groupId, string $studentProfileId): void
    {
        DB::table('group_memberships')->insert([
            'id' => (string) Str::ulid(),
            'group_id' => $groupId,
            'student_profile_id' => $studentProfileId,
            'joined_at' => now(),
            'status' => 'active',
            'created_at' => now(),
        ]);
    }

    private function grantPermission(User $user, string $permissionName): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => GuardName::Web->value, 'module' => 'Messaging'],
        );

        ModelHasPermission::query()->create([
            'permission_id' => (string) $permission->getKey(),
            'model_type' => $user->getMorphClass(),
            'model_id' => (string) $user->getAuthIdentifier(),
        ]);

        app(PermissionGateRegistrar::class)->register();
    }
}
