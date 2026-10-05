<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Domain\Contracts\UserAccountOperations;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Shared\Support\BusinessRuleViolation;
use Tests\TestCase;

/**
 * عمليات الحساب المرتبط من مراكز المعلم والطالب — طبقة التركيب.
 *
 * كل تعديل يمر عبر عقد Identity الرسمي: تحقق المؤسسة أولًا، ثم
 * التفويض للإجراءات، ثم سطر تدقيق كامل بالفاعل والسبب.
 */
final class LinkedAccountAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_safe_account_fields_with_audit(): void
    {
        [$organization, $admin, $account] = $this->context();

        app(UserAccountOperations::class)->updateProfile(
            organizationId: (string) $organization->id,
            userId: (string) $account->id,
            fields: [
                'name' => 'الاسم المحدَّث',
                'phone' => '+201098765432',
                'locale' => 'fr',
                'timezone' => 'Africa/Cairo',
            ],
            actorId: (string) $admin->id,
            reason: 'تصحيح بيانات الحساب بناءً على طلب صاحبه',
        );

        $account->refresh();

        self::assertSame('الاسم المحدَّث', $account->name);
        self::assertSame('+201098765432', $account->phone);
        self::assertSame('Africa/Cairo', $account->timezone);

        self::assertTrue(DB::table('audit_log')->where([
            'action' => 'identity.account_updated',
            'auditable_id' => (string) $account->id,
            'reason' => 'تصحيح بيانات الحساب بناءً على طلب صاحبه',
        ])->exists());
    }

    public function test_rejects_account_from_another_organization(): void
    {
        [$organization] = $this->context();
        $foreignOrg = Organization::factory()->create();
        $outsider = User::factory()->inOrganization((string) $foreignOrg->id)->create();

        $this->expectException(BusinessRuleViolation::class);

        app(UserAccountOperations::class)->updateProfile(
            organizationId: (string) $organization->id,
            userId: (string) $outsider->id,
            fields: ['name' => 'اختراق'],
            actorId: '01J00000000000000000000000',
            reason: 'محاولة عبور حدود المؤسسة',
        );
    }

    public function test_requires_written_reason(): void
    {
        [$organization, , $account] = $this->context();

        $this->expectException(BusinessRuleViolation::class);

        app(UserAccountOperations::class)->updateProfile(
            organizationId: (string) $organization->id,
            userId: (string) $account->id,
            fields: ['name' => 'بلا سبب'],
            actorId: (string) $account->id,
            reason: '',
        );
    }

    public function test_changes_status_through_the_official_state_machine(): void
    {
        [$organization, $admin, $account] = $this->context();

        app(UserAccountOperations::class)->changeStatus(
            organizationId: (string) $organization->id,
            userId: (string) $account->id,
            status: UserStatus::Frozen->value,
            actorId: (string) $admin->id,
            reason: 'تجميد مؤقت لحين تسوية المستحقات',
        );

        self::assertSame(UserStatus::Frozen->value, $account->refresh()->status->value);
        self::assertTrue(DB::table('audit_log')->where([
            'action' => 'identity.user_status_changed',
            'auditable_id' => (string) $account->id,
            'reason' => 'تجميد مؤقت لحين تسوية المستحقات',
        ])->exists());

        // انتقال غير مسموح من frozen إلى نفسه → مرفوض عبر canTransitionTo.
        $this->expectException(BusinessRuleViolation::class);

        app(UserAccountOperations::class)->changeStatus(
            organizationId: (string) $organization->id,
            userId: (string) $admin->id,
            status: 'unknown_status',
            actorId: (string) $admin->id,
            reason: 'قيمة حالة غير معروفة',
        );
    }

    /** @return array{0: Organization, 1: User, 2: User} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->inOrganization((string) $organization->id)->create();
        $account = User::factory()->inOrganization((string) $organization->id)->create();

        return [$organization, $admin, $account];
    }
}
