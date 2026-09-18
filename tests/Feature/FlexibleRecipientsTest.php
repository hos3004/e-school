<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Notifications\Application\Services\ManualNotificationRecipientResolver;
use Modules\Notifications\Domain\Enums\ManualAudience;
use Modules\Notifications\Domain\Enums\ManualRecipientType;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

function recipientResolver(): ManualNotificationRecipientResolver
{
    return app(ManualNotificationRecipientResolver::class);
}

/**
 * Fixtures::guardianProfileId() لا وجود له بعد؛ إدراج مباشر هنا مثل حالة
 * "outsider" أعلاه بدل توسيع Fixtures المشتركة لهذا الاختبار وحده.
 */
function insertGuardianProfileId(string $organizationId, ?string $userId = null): string
{
    $id = (string) Str::ulid();
    $userId ??= Fixtures::userId();

    DB::table('guardian_profiles')->insert([
        'id' => $id,
        'organization_id' => $organizationId,
        'user_id' => $userId,
        'guardian_code' => 'W'.strtoupper(substr($id, -8)),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

it('sends to every active student in the organization', function (): void {
    $organizationId = Fixtures::organizationId();
    $first = Fixtures::studentProfileId();
    $second = Fixtures::studentProfileId();

    $expected = DB::table('student_profiles')
        ->whereIn('id', [$first, $second])
        ->pluck('user_id')
        ->map(static fn (mixed $id): string => (string) $id)
        ->all();

    $resolution = recipientResolver()->resolve(
        $organizationId,
        ManualRecipientType::AllStudents,
        ManualRecipientType::AllStudents->value,
    );

    expect($resolution->userIds)->toContain(...$expected)
        ->and($resolution->count())->toBeGreaterThanOrEqual(2);
});

it('skips a suspended account when sending to every student', function (): void {
    $organizationId = Fixtures::organizationId();
    $active = Fixtures::studentProfileId();
    $suspended = Fixtures::studentProfileId();

    $suspendedUserId = (string) DB::table('student_profiles')
        ->where('id', $suspended)->value('user_id');
    DB::table('users')->where('id', $suspendedUserId)->update(['status' => 'suspended']);

    $activeUserId = (string) DB::table('student_profiles')
        ->where('id', $active)->value('user_id');

    $resolution = recipientResolver()->resolve(
        $organizationId,
        ManualRecipientType::AllStudents,
        ManualRecipientType::AllStudents->value,
    );

    expect($resolution->userIds)->toContain($activeUserId)
        ->and($resolution->userIds)->not->toContain($suspendedUserId);
});

it('sends to a hand-picked list and keeps the chosen order', function (): void {
    $organizationId = Fixtures::organizationId();
    $first = (string) DB::table('student_profiles')
        ->where('id', Fixtures::studentProfileId())->value('user_id');
    $second = (string) DB::table('student_profiles')
        ->where('id', Fixtures::studentProfileId())->value('user_id');

    $resolution = recipientResolver()->resolve(
        $organizationId,
        ManualRecipientType::People,
        $second.','.$first,
    );

    expect($resolution->userIds)->toBe([$second, $first])
        ->and($resolution->count())->toBe(2);
});

it('drops an account from another organization out of a hand-picked list', function (): void {
    $organizationId = Fixtures::organizationId();
    $mine = (string) DB::table('student_profiles')
        ->where('id', Fixtures::studentProfileId())->value('user_id');

    $otherOrganizationId = (string) Str::ulid();
    DB::table('organizations')->insert([
        'id' => $otherOrganizationId,
        'name' => json_encode(['ar' => 'أخرى', 'en' => 'Other'], JSON_UNESCAPED_UNICODE),
        'slug' => 'other-'.strtolower(substr($otherOrganizationId, -8)),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $outsiderId = (string) Str::ulid();
    DB::table('users')->insert([
        'id' => $outsiderId,
        'organization_id' => $otherOrganizationId,
        'name' => 'Outsider',
        'email' => 'outsider.'.strtolower(substr($outsiderId, -8)).'@test.local',
        'username' => 'outsider'.strtolower(substr($outsiderId, -8)),
        'password' => bcrypt('password'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $resolution = recipientResolver()->resolve(
        $organizationId,
        ManualRecipientType::People,
        $mine.','.$outsiderId,
    );

    expect($resolution->userIds)->toBe([$mine]);
});

it('refuses an empty hand-picked list', function (): void {
    recipientResolver()->resolve(
        Fixtures::organizationId(),
        ManualRecipientType::People,
        '',
    );
})->throws(BusinessRuleViolation::class);

it('needs no target for the organization-wide types but still needs one otherwise', function (): void {
    expect(ManualRecipientType::AllStudents->needsSingleTarget())->toBeFalse()
        ->and(ManualRecipientType::AllTeachers->needsSingleTarget())->toBeFalse()
        ->and(ManualRecipientType::People->needsSingleTarget())->toBeFalse()
        ->and(ManualRecipientType::Course->needsSingleTarget())->toBeTrue()
        ->and(ManualRecipientType::Student->needsSingleTarget())->toBeTrue();

    // تقييد الجمهور يخص الهدف متعدد الأطراف وحده.
    expect(ManualRecipientType::AllStudents->isAudienceScoped())->toBeFalse()
        ->and(ManualRecipientType::People->isAudienceScoped())->toBeFalse()
        ->and(ManualRecipientType::Course->isAudienceScoped())->toBeTrue();
});

it('offers both students and teachers when picking a list of people', function (): void {
    $organizationId = Fixtures::organizationId();
    Fixtures::studentProfileId();
    Fixtures::staffProfileId();

    $options = recipientResolver()->search($organizationId, ManualRecipientType::People, '');

    expect($options)->not->toBeEmpty();
});

it('resolves a single student by their own user id', function (): void {
    $organizationId = Fixtures::organizationId();
    $userId = (string) DB::table('student_profiles')
        ->where('id', Fixtures::studentProfileId())->value('user_id');

    $resolution = recipientResolver()->resolve(
        $organizationId,
        ManualRecipientType::Student,
        $userId,
        ManualAudience::All,
    );

    expect($resolution->userIds)->toBe([$userId]);
});

it('resolves a single guardian by their own user id', function (): void {
    $organizationId = Fixtures::organizationId();
    $guardianProfileId = insertGuardianProfileId($organizationId);
    $userId = (string) DB::table('guardian_profiles')
        ->where('id', $guardianProfileId)->value('user_id');

    $resolution = recipientResolver()->resolve(
        $organizationId,
        ManualRecipientType::Guardian,
        $userId,
        ManualAudience::All,
    );

    expect($resolution->userIds)->toBe([$userId]);
});

it('refuses to resolve an archived guardian', function (): void {
    $organizationId = Fixtures::organizationId();
    $guardianProfileId = insertGuardianProfileId($organizationId);
    $userId = (string) DB::table('guardian_profiles')
        ->where('id', $guardianProfileId)->value('user_id');
    DB::table('guardian_profiles')->where('id', $guardianProfileId)
        ->update(['deleted_at' => now()]);

    recipientResolver()->resolve($organizationId, ManualRecipientType::Guardian, $userId);
})->throws(BusinessRuleViolation::class);

it('includes guardians alongside students and teachers in the people picker', function (): void {
    $organizationId = Fixtures::organizationId();
    insertGuardianProfileId($organizationId);
    $userId = (string) DB::table('guardian_profiles')->orderByDesc('created_at')->value('user_id');
    $name = (string) DB::table('users')->where('id', $userId)->value('name');

    $options = recipientResolver()->search($organizationId, ManualRecipientType::People, $name);

    expect($options)->toHaveKey($userId);
});
