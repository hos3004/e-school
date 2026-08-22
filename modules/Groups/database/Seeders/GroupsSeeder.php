<?php

declare(strict_types=1);

namespace Modules\Groups\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Enums\GroupTeacherRole;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupMembership;
use Modules\Groups\Domain\Models\GroupTeacher;

/**
 * بيانات تجريبية للمجموعات.
 *
 * يعيد استخدام المؤسسة التجريبية إن وُجدت ولا ينشئ كيانات لموديولات أخرى —
 * المعرّفات الخارجية (طلاب/معلمين/برامج) تُولَّد كقيم فقط لأن هذا بذر عرض.
 */
final class GroupsSeeder extends Seeder
{
    public function run(): void
    {
        $organizationId = $this->ensureOrganizationId();

        $groups = [
            [
                'code' => 'GRP-2026-001',
                'name' => ['ar' => __('groups::messages.demo_group_name', ['n' => 1]), 'en' => 'Demo Group 1'],
                'capacity' => 12,
                'status' => GroupStatus::Active,
            ],
            [
                'code' => 'GRP-2026-002',
                'name' => ['ar' => __('groups::messages.demo_group_name', ['n' => 2]), 'en' => 'Demo Group 2'],
                'capacity' => 16,
                'status' => GroupStatus::Active,
            ],
            [
                'code' => 'GRP-2026-003',
                'name' => ['ar' => __('groups::messages.demo_group_name', ['n' => 3]), 'en' => 'Demo Group 3'],
                'capacity' => 20,
                'status' => GroupStatus::Planning,
            ],
        ];

        foreach ($groups as $index => $data) {
            $group = Group::query()->firstOrCreate(
                ['code' => $data['code']],
                array_merge($data, [
                    'organization_id' => $organizationId,
                    'timezone' => config('app.timezone') ?? 'UTC',
                    'starts_on' => now()->addDays($index * 7)->toDateString(),
                    'ends_on' => null,
                ]),
            );

            if (!$group->wasRecentlyCreated) {
                continue;
            }

            GroupTeacher::query()->create([
                'group_id' => (string) $group->getKey(),
                'staff_profile_id' => (string) str()->ulid(),
                'course_id' => null,
                'role' => $index === 0 ? GroupTeacherRole::Lead : GroupTeacherRole::Assistant,
                'assigned_from' => now()->toDateString(),
            ]);

            for ($seat = 0; $seat < min(5, (int) $group->capacity); $seat++) {
                GroupMembership::query()->create([
                    'group_id' => (string) $group->getKey(),
                    'student_profile_id' => (string) str()->ulid(),
                    'joined_at' => now()->subDays($seat + 1),
                    'status' => MembershipStatus::Active,
                ]);
            }
        }
    }

    /**
     * المؤسسة يملكها موديول Organization — نستهلك الموجودة، وإن لم توجد
     * أنشئ سجلًا تجريبيًا مصغرًا كما يفعل باقي الموديولات في بيئة العرض.
     */
    private function ensureOrganizationId(): string
    {
        $existing = DB::table('organizations')->orderBy('created_at')->value('id');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $organizationId = '01JDEMOORGANIZATION0000000';

        DB::table('organizations')->insert([
            'id' => $organizationId,
            'name' => json_encode(['ar' => __('groups::messages.demo_school_name'), 'en' => 'Demo School'], JSON_UNESCAPED_UNICODE),
            'slug' => 'demo-school',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $organizationId;
    }
}
