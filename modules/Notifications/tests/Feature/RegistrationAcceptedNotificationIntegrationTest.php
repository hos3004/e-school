<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Students\Domain\Events\RegistrationAccepted;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

it('queues the real registration acceptance event for every enabled phase one channel', function (): void {
    config(['notifications.channels.whatsapp.enabled' => true]);

    $recipientId = Fixtures::userId();
    $organizationId = Fixtures::organizationId();

    DB::table('users')->where('id', $recipientId)->update([
        'email' => 'accepted-student@example.test',
        'phone' => '+201001234567',
        'phone_country' => 'EG',
        'locale' => 'ar',
        'timezone' => 'Africa/Cairo',
    ]);

    Event::dispatch(new RegistrationAccepted(
        applicationId: (string) Str::ulid(),
        organizationId: $organizationId,
        studentProfileId: (string) Str::ulid(),
        studentUserId: $recipientId,
        actorId: Fixtures::userId(),
    ));

    $outbox = NotificationOutbox::query()
        ->where('user_id', $recipientId)
        ->where('event_name', 'registration.approved')
        ->get();

    expect($outbox)->toHaveCount(3)
        ->and($outbox->pluck('channel')->sort()->values()->all())
        ->toBe(['email', 'in_app', 'whatsapp'])
        ->and($outbox->every(
            static fn (NotificationOutbox $row): bool => $row->status === OutboxStatus::Queued
                && $row->category === 'registration_update'
                && data_get($row->payload, 'student_user_id') === $recipientId,
        ))->toBeTrue();
});
