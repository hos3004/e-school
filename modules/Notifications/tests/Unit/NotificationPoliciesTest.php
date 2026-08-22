<?php

declare(strict_types=1);

use Modules\Notifications\Application\Policies\NotificationDeliveryAttemptPolicy;
use Modules\Notifications\Application\Policies\NotificationOutboxPolicy;
use Modules\Notifications\Application\Policies\NotificationPreferencePolicy;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationDeliveryAttempt;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Domain\Models\NotificationPreference;

/**
 * مستخدم تجريبي بلا دور مسمّى — السياسات تعتمد can() والملكية فقط.
 */
final class PolicyUserStub
{
    public function __construct(
        private readonly array $abilities,
        public readonly ?string $organization_id,
        public readonly ?string $id,
    ) {}

    public function can(string $ability, mixed $arguments = []): bool
    {
        return in_array($ability, $this->abilities, true);
    }
}

function outboxPolicyRecord(string $organizationId, string $userId): NotificationOutbox
{
    return NotificationOutbox::query()->make([
        'organization_id' => $organizationId,
        'user_id' => $userId,
        'status' => OutboxStatus::Queued,
    ]);
}

it('grants outbox access only inside the same organization', function (): void {
    $policy = new NotificationOutboxPolicy;

    $record = outboxPolicyRecord('org-1', 'user-9');
    $admin = new PolicyUserStub(
        ['notifications.outbox.view', 'notifications.outbox.view_any', 'notifications.outbox.cancel'],
        'org-1',
        'staff-1',
    );
    $foreign = new PolicyUserStub(
        ['notifications.outbox.view', 'notifications.outbox.view_any', 'notifications.outbox.cancel'],
        'org-2',
        'staff-2',
    );

    expect($policy->view($admin, $record))->toBeTrue()
        ->and($policy->view($foreign, $record))->toBeFalse()
        ->and($policy->cancel($admin, $record))->toBeTrue()
        ->and($policy->cancel($foreign, $record))->toBeFalse()
        ->and($policy->viewAny($foreign))->toBeTrue();
});

it('lets the recipient always view their own notification without admin powers', function (): void {
    $policy = new NotificationOutboxPolicy;

    $record = outboxPolicyRecord('org-1', 'user-9');
    $owner = new PolicyUserStub([], 'org-1', 'user-9');
    $stranger = new PolicyUserStub([], 'org-1', 'user-8');

    expect($policy->viewOwn($owner, $record))->toBeTrue()
        ->and($policy->viewOwn($stranger, $record))->toBeFalse();
});

it('guards preference ownership for self-service updates', function (): void {
    $policy = new NotificationPreferencePolicy;

    $preference = NotificationPreference::query()->make([
        'organization_id' => 'org-1',
        'user_id' => 'user-9',
        'enabled' => true,
    ]);

    $owner = new PolicyUserStub([], 'org-1', 'user-9');
    $stranger = new PolicyUserStub(['notifications.preference.update'], 'org-1', 'user-8');

    expect($policy->updateOwn($owner, $preference))->toBeTrue()
        ->and($policy->updateOwn($stranger, $preference))->toBeFalse()
        ->and($policy->update($stranger, $preference))->toBeTrue()
        ->and($policy->update($owner, $preference))->toBeFalse();
});

it('keeps delivery attempts read-only', function (): void {
    $policy = new NotificationDeliveryAttemptPolicy;

    $attempt = NotificationDeliveryAttempt::query()->make([
        'organization_id' => 'org-1',
        'succeeded' => false,
    ]);

    $viewer = new PolicyUserStub(['notifications.attempt.view'], 'org-1', null);
    $foreign = new PolicyUserStub(['notifications.attempt.view'], 'org-2', null);

    expect($policy->view($viewer, $attempt))->toBeTrue()
        ->and($policy->view($foreign, $attempt))->toBeFalse()
        ->and(method_exists($policy, 'create'))->toBeFalse()
        ->and(method_exists($policy, 'update'))->toBeFalse()
        ->and(method_exists($policy, 'delete'))->toBeFalse();
});
