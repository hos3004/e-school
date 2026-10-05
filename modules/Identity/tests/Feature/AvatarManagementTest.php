<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Identity\Domain\Contracts\AvatarQueries;
use Modules\Identity\Domain\Contracts\UserAccountOperations;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Shared\Support\BusinessRuleViolation;
use Tests\TestCase;

final class AvatarManagementTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_BYTES_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function test_resolves_default_avatars_by_gender(): void
    {
        $resolver = app(AvatarQueries::class);

        $male = $resolver->defaultUrl('male');
        $female = $resolver->defaultUrl('female');
        $neutral = $resolver->defaultUrl(null);
        $unknown = $resolver->defaultUrl('other');

        self::assertStringContainsString('default-male.png', $male);
        self::assertStringContainsString('default-female.png', $female);
        self::assertNotSame($male, $female);

        // القيم غير المحددة أو غير المعروفة ← الصورة المحايدة نفسها.
        self::assertSame($neutral, $unknown);
        self::assertSame(
            $resolver->resolve(null, 'male')->url,
            $male,
        );
        self::assertTrue($resolver->resolve(null, null)->isDefault);
    }

    public function test_uploads_and_replaces_avatar_deleting_the_old_file_only_after_success(): void
    {
        [$organization, $user] = $this->context();
        $disk = Storage::disk((string) config('avatars.disk'));
        $operations = app(UserAccountOperations::class);

        // أول صورة
        $firstTmp = 'avatars/tmp/first.png';
        $disk->put($firstTmp, self::pngBytes());

        $operations->setAvatar(
            organizationId: (string) $organization->id,
            userId: (string) $user->id,
            storedPath: $firstTmp,
            actorId: (string) $user->id,
            reason: 'تحديث صورة الحساب بناء على طلب المستخدم',
        );

        $user->refresh();
        /** @var string $firstFinal */
        $firstFinal = $user->avatar_path;

        self::assertNotNull($firstFinal);
        self::assertStringStartsWith('avatars/', $firstFinal);
        self::assertFalse(str_contains($firstFinal, 'first')); // لا يكشف الاسم الأصلي
        self::assertTrue($disk->exists($firstFinal));
        self::assertFalse($disk->exists($firstTmp)); // انتقل من المؤقت

        // استبدال بالثانية
        $secondTmp = 'avatars/tmp/second.png';
        $disk->put($secondTmp, self::pngBytes());

        $operations->setAvatar(
            organizationId: (string) $organization->id,
            userId: (string) $user->id,
            storedPath: $secondTmp,
            actorId: (string) $user->id,
            reason: 'استبدال الصورة بطلب المستخدم',
        );

        $user->refresh();

        self::assertNotSame($firstFinal, $user->avatar_path);
        self::assertTrue($disk->exists((string) $user->avatar_path));
        self::assertFalse($disk->exists($firstFinal)); // القديمة نُظّفت بعد نجاح العملية فقط

        self::assertTrue(DB::table('audit_log')->where([
            'action' => 'identity.avatar_updated',
            'auditable_id' => (string) $user->id,
        ])->exists());
    }

    public function test_rejects_disguised_files_that_are_not_real_images(): void
    {
        [$organization, $user] = $this->context();
        $disk = Storage::disk((string) config('avatars.disk'));

        $tmp = 'avatars/tmp/fake.png';
        $disk->put($tmp, 'definitely not an image payload');

        try {
            app(UserAccountOperations::class)->setAvatar(
                organizationId: (string) $organization->id,
                userId: (string) $user->id,
                storedPath: $tmp,
                actorId: (string) $user->id,
                reason: 'محاولة رفع ملف مزوّر',
            );

            self::fail('Expected BusinessRuleViolation for a non-image file.');
        } catch (BusinessRuleViolation) {
            $user->refresh();

            self::assertNull($user->avatar_path);
            self::assertFalse(DB::table('audit_log')->where([
                'action' => 'identity.avatar_updated',
                'auditable_id' => (string) $user->id,
            ])->exists());
        }
    }

    public function test_rejects_operations_on_accounts_from_another_organization(): void
    {
        [$organization, $user] = $this->context();
        $otherOrganization = Organization::factory()->create();

        $this->expectException(BusinessRuleViolation::class);

        app(UserAccountOperations::class)->setAvatar(
            organizationId: (string) $otherOrganization->id,
            userId: (string) $user->id,
            storedPath: null,
            actorId: (string) $user->id,
            reason: 'محاولة عبر مؤسسة أخرى',
        );
    }

    public function test_reason_is_mandatory(): void
    {
        [$organization, $user] = $this->context();

        $this->expectException(BusinessRuleViolation::class);

        app(UserAccountOperations::class)->setAvatar(
            organizationId: (string) $organization->id,
            userId: (string) $user->id,
            storedPath: null,
            actorId: (string) $user->id,
            reason: '',
        );
    }

    /**
     * @return array{0: Organization, 1: User}
     */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->inOrganization((string) $organization->id)->create();

        return [$organization, $user];
    }

    private static function pngBytes(): string
    {
        return (string) base64_decode(self::PNG_BYTES_BASE64, true);
    }
}
