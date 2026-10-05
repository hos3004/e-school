<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Application\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modules\VirtualClassroom\Domain\Contracts\VirtualClassroomProvider;
use Modules\VirtualClassroom\Domain\Enums\ClassroomHealthStatus;
use Modules\VirtualClassroom\Domain\Enums\JoinRole;
use Modules\VirtualClassroom\Domain\Exceptions\ClassroomProviderException;
use Modules\VirtualClassroom\Domain\ValueObjects\ClassroomSpec;
use Modules\VirtualClassroom\Domain\ValueObjects\JoinRequest;

/** فحص حي للمزوّد لا يكتب في قاعدة بيانات المنصة ولا يطبع السر. */
final class SmokeTestClassroom extends Command
{
    protected $signature = 'classroom:smoke-test
                            {action=full : health|create|status|end|recordings|full}
                            {--meeting= : External meeting identifier for status, end, or recordings}
                            {--moderator-password= : Moderator password required by BBB when ending an existing meeting}
                            {--title= : Test classroom title}
                            {--name= : Display name for generated join URLs}
                            {--record : Request recording for a newly created classroom}';

    protected $description = 'Classroom provider smoke test.';

    public function handle(VirtualClassroomProvider $provider): int
    {
        $this->printConfiguration($provider);
        $action = (string) $this->argument('action');

        try {
            return match ($action) {
                'health' => $this->health($provider),
                'create' => $this->create($provider),
                'status' => $this->status($provider),
                'end' => $this->end($provider),
                'recordings' => $this->recordings($provider),
                'full' => $this->full($provider),
                default => $this->unknownAction($action),
            };
        } catch (ClassroomProviderException $exception) {
            $this->error($exception->getMessage());
            $this->line(__('virtualclassroom::messages.smoke_reason', ['reason' => $exception->reason]));

            return self::FAILURE;
        }
    }

    private function printConfiguration(VirtualClassroomProvider $provider): void
    {
        $configuration = (array) config('virtual-classroom.providers.'.$provider->name(), []);
        $secret = (string) ($configuration['secret'] ?? '');

        $this->line(__('virtualclassroom::messages.smoke_config', [
            'provider' => $provider->name(),
            'base_url' => (string) ($configuration['base_url'] ?? '—'),
            'fingerprint' => $secret === '' ? '—' : substr(sha1($secret), 0, 12),
        ]));
    }

    private function health(VirtualClassroomProvider $provider): int
    {
        $health = $provider->healthCheck();

        if ($health->status !== ClassroomHealthStatus::Healthy) {
            $this->error(__('virtualclassroom::messages.smoke_health_failed', [
                'reason' => $health->message ?? $health->status->value,
            ]));

            return self::FAILURE;
        }

        $this->info(__('virtualclassroom::messages.smoke_health_ok'));

        return self::SUCCESS;
    }

    private function create(VirtualClassroomProvider $provider): int
    {
        $remote = $provider->createClassroom($this->spec($this->newMeetingId()));
        $this->info(__('virtualclassroom::messages.smoke_created', ['meeting' => $remote->externalId]));
        $this->printJoinUrls($provider, $remote->externalId, $remote->moderatorSecret, $remote->attendeeSecret);

        return self::SUCCESS;
    }

    private function status(VirtualClassroomProvider $provider): int
    {
        $meetingId = $this->requiredMeetingId();
        if ($meetingId === null) {
            return self::FAILURE;
        }

        $isRunning = $provider->isRunning($meetingId);
        $this->line($isRunning
            ? __('virtualclassroom::messages.smoke_running')
            : __('virtualclassroom::messages.smoke_not_running'));

        if (!$isRunning) {
            return self::SUCCESS;
        }

        $this->line(__('virtualclassroom::messages.smoke_participants', [
            'count' => count($provider->participants($meetingId)),
        ]));

        return self::SUCCESS;
    }

    private function end(VirtualClassroomProvider $provider): int
    {
        $meetingId = $this->requiredMeetingId();
        if ($meetingId === null) {
            return self::FAILURE;
        }

        $moderatorSecret = $this->option('moderator-password');
        $provider->endClassroom(
            $meetingId,
            is_string($moderatorSecret) && $moderatorSecret !== '' ? $moderatorSecret : null,
        );
        $this->info(__('virtualclassroom::messages.smoke_ended', ['meeting' => $meetingId]));

        return self::SUCCESS;
    }

    private function recordings(VirtualClassroomProvider $provider): int
    {
        $meetingId = $this->requiredMeetingId();
        if ($meetingId === null) {
            return self::FAILURE;
        }

        $this->line(__('virtualclassroom::messages.smoke_recordings', [
            'count' => count($provider->recordings($meetingId)),
        ]));

        return self::SUCCESS;
    }

    private function full(VirtualClassroomProvider $provider): int
    {
        if ($this->health($provider) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $remote = $provider->createClassroom($this->spec($this->newMeetingId()));
        $this->info(__('virtualclassroom::messages.smoke_created', ['meeting' => $remote->externalId]));
        $this->printJoinUrls($provider, $remote->externalId, $remote->moderatorSecret, $remote->attendeeSecret);
        $provider->endClassroom($remote->externalId, $remote->moderatorSecret);
        $this->info(__('virtualclassroom::messages.smoke_ended', ['meeting' => $remote->externalId]));

        return self::SUCCESS;
    }

    private function spec(string $meetingId): ClassroomSpec
    {
        $title = $this->option('title');

        return new ClassroomSpec(
            sessionId: $meetingId,
            externalMeetingId: $meetingId,
            title: is_string($title) && $title !== ''
                ? $title
                : (string) __('virtualclassroom::messages.smoke_default_title'),
            startsAt: null,
            maxParticipants: (int) config('virtual-classroom.capacity.max_participants_group'),
            recordable: (bool) $this->option('record'),
        );
    }

    private function printJoinUrls(
        VirtualClassroomProvider $provider,
        string $meetingId,
        string $moderatorSecret,
        string $attendeeSecret,
    ): void {
        $name = $this->option('name');
        $displayName = is_string($name) && $name !== ''
            ? $name
            : (string) __('virtualclassroom::messages.smoke_default_name');

        $this->line(__('virtualclassroom::messages.smoke_join_moderator'));
        $this->line($provider->generateJoinUrl(new JoinRequest(
            externalId: $meetingId,
            displayName: $displayName.' (moderator)',
            role: JoinRole::Moderator,
            rolePassword: $moderatorSecret,
            externalUserId: 'smoke-moderator',
        )));
        $this->line(__('virtualclassroom::messages.smoke_join_viewer'));
        $this->line($provider->generateJoinUrl(new JoinRequest(
            externalId: $meetingId,
            displayName: $displayName.' (viewer)',
            role: JoinRole::Viewer,
            rolePassword: $attendeeSecret,
            externalUserId: 'smoke-viewer',
        )));
    }

    private function newMeetingId(): string
    {
        return 'SMOKE-'.Str::upper(Str::random(10));
    }

    private function requiredMeetingId(): ?string
    {
        $meetingId = $this->option('meeting');

        if (!is_string($meetingId) || $meetingId === '') {
            $this->error(__('virtualclassroom::messages.smoke_meeting_required'));

            return null;
        }

        return $meetingId;
    }

    private function unknownAction(string $action): int
    {
        $this->error(__('virtualclassroom::messages.smoke_unknown_action', ['action' => $action]));

        return self::FAILURE;
    }
}
