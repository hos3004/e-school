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
    /** @param list<string> $abilities */
    public function __construct(
        private readonly array $abilities,
        public readonly ?string $organization_id,
        public readonly ?string $id,
    ) {}

    public function can(string $ability, mixed $arguments = []): bool
    {
        return in_array($ability, $this->abilities, true);
    }

    public function getAuthIdentifier(): ?string
    {
        return $this->id;
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
        ['settings.manage', 'notifications.outbox.cancel'],
        'org-1',
        'staff-1',
    );
    $foreign = new PolicyUserStub(
        ['settings.manage', 'notifications.outbox.cancel'],
        'org-2',
        'staff-2',
    );

    expect($policy->view($admin, $record))->toBeTrue()
        ->and($policy->view($foreign, $record))->toBeFalse()
        ->and($policy->cancel($admin, $record))->toBeTrue()
        ->and($policy->cancel($foreign, $record))->toBeFalse()
        ->and($policy->viewAny($foreign))->toBeTrue();
});

it('allows read controls only for the delivered in-app record owner in the same organization', function (): void {
    $policy = new NotificationOutboxPolicy;
    $record = NotificationOutbox::query()->make([
        'organization_id' => 'org-1',
        'user_id' => 'user-9',
        'channel' => 'in_app',
        'status' => OutboxStatus::Sent,
    ]);

    $owner = new PolicyUserStub([], 'org-1', 'user-9');
    $foreignTenant = new PolicyUserStub([], 'org-2', 'user-9');
    $stranger = new PolicyUserStub([], 'org-1', 'user-8');

    expect($policy->listOwn($owner))->toBeTrue()
        ->and($policy->markAllAsRead($owner))->toBeTrue()
        ->and($policy->markAsRead($owner, $record))->toBeTrue()
        ->and($policy->markAsRead($foreignTenant, $record))->toBeFalse()
        ->and($policy->markAsRead($stranger, $record))->toBeFalse();
});

it('restricts manual resend to settings managers inside the record organization', function (): void {
    $policy = new NotificationOutboxPolicy;
    $record = outboxPolicyRecord('org-1', 'user-9');
    $manager = new PolicyUserStub(['settings.manage'], 'org-1', 'staff-1');
    $foreignManager = new PolicyUserStub(['settings.manage'], 'org-2', 'staff-2');
    $ordinaryUser = new PolicyUserStub([], 'org-1', 'staff-3');

    expect($policy->retry($manager, $record))->toBeTrue()
        ->and($policy->retry($foreignManager, $record))->toBeFalse()
        ->and($policy->retry($ordinaryUser, $record))->toBeFalse();
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

    $methods = get_class_methods($policy);

    expect($policy->view($viewer, $attempt))->toBeTrue()
        ->and($policy->view($foreign, $attempt))->toBeFalse()
        ->and($methods)->not->toContain('create', 'update', 'delete');
});
