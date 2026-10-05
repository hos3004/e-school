<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Application\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Sessions\Domain\ValueObjects\SessionAdministrationData;
use Modules\VirtualClassroom\Application\Actions\ProvisionClassroomAction;
use Modules\VirtualClassroom\Domain\Contracts\ClassroomAdministrationQueries;
use Modules\VirtualClassroom\Domain\Exceptions\ClassroomProviderException;

final class ProvisionUpcomingClassrooms extends Command
{
    protected $signature = 'classroom:provision-upcoming';

    protected $description = 'Provision live classrooms shortly before their scheduled start.';

    public function handle(
        SessionAdministrationQueries $sessions,
        ClassroomAdministrationQueries $classrooms,
        ProvisionClassroomAction $provision,
    ): int {
        $now = CarbonImmutable::now('UTC');
        $until = $now->addMinutes((int) config('virtual-classroom.provisioning.before_minutes', 20));
        $items = $sessions->upcomingForClassroomProvisioning(
            $now,
            $until,
            (int) config('virtual-classroom.provisioning.batch_size', 100),
        );
        $provisioned = 0;
        $failed = 0;

        foreach ($items as $session) {
            $existing = $classrooms->findForSession($session->organizationId, $session->id);
            if ($existing !== null && in_array($existing->status, ['provisioned', 'running', 'ended'], true)) {
                continue;
            }

            try {
                $provision->execute(
                    sessionId: $session->id,
                    title: $this->localized($session),
                    startsAt: CarbonImmutable::parse($session->scheduledStart)->utc(),
                    organizationId: $session->organizationId,
                    reason: __('virtualclassroom::messages.scheduled_provision_reason'),
                );
                $provisioned++;
            } catch (ClassroomProviderException $exception) {
                $failed++;
                logger()->warning('Classroom provisioning failed.', [
                    'session_id' => $session->id,
                    'organization_id' => $session->organizationId,
                    'reason' => $exception->reason,
                ]);
            }
        }

        $this->info(__('virtualclassroom::messages.provisioning_summary', [
            'provisioned' => $provisioned,
            'failed' => $failed,
        ]));

        return self::SUCCESS;
    }

    private function localized(SessionAdministrationData $session): string
    {
        return $session->title[app()->getLocale()]
            ?? $session->title['ar']
            ?? $session->title['en']
            ?? __('virtualclassroom::messages.default_classroom_title');
    }
}
