<?php

declare(strict_types=1);

namespace App\Application\Actions;

use Modules\Sessions\Domain\Models\Session;
use Modules\VirtualClassroom\Application\Actions\CheckClassroomHealthAction;
use Modules\VirtualClassroom\Application\Actions\ProvisionClassroomAction;
use Modules\VirtualClassroom\Domain\Exceptions\ClassroomProviderException;
use Shared\Support\BusinessRuleViolation;

/** منسق app-level لعمليات الفصل التي تبدأ من مركز الحصة. */
final readonly class SessionClassroomOperations
{
    public function __construct(
        private ProvisionClassroomAction $provision,
        private CheckClassroomHealthAction $health,
    ) {}

    public function provision(Session $session, string $actorId, string $reason): void
    {
        try {
            $this->provision->execute(
                sessionId: (string) $session->getKey(),
                title: $this->localized(is_array($session->title) ? $session->title : []),
                maxParticipants: $this->capacity((string) $session->session_type),
                startsAt: $session->scheduled_start,
                organizationId: (string) $session->organization_id,
                actorId: $actorId,
                reason: $reason,
            );
        } catch (ClassroomProviderException $exception) {
            throw BusinessRuleViolation::make(
                'virtualclassroom.'.$exception->reason,
                'virtualclassroom::errors.provider_unavailable',
            );
        }
    }

    public function checkHealth(Session $session, string $actorId, string $reason): void
    {
        try {
            $this->health->execute(
                organizationId: (string) $session->organization_id,
                sessionId: (string) $session->getKey(),
                actorId: $actorId,
                reason: $reason,
            );
        } catch (ClassroomProviderException $exception) {
            throw BusinessRuleViolation::make(
                'virtualclassroom.'.$exception->reason,
                'virtualclassroom::errors.provider_unavailable',
            );
        }
    }

    private function capacity(string $sessionType): int
    {
        $key = match ($sessionType) {
            'individual' => 'max_participants_individual',
            'webinar' => 'max_participants_webinar',
            default => 'max_participants_group',
        };

        return (int) config('virtual-classroom.capacity.'.$key);
    }

    /** @param array<string, string> $value */
    private function localized(array $value): string
    {
        return $value[app()->getLocale()]
            ?? $value['ar']
            ?? $value['en']
            ?? __('virtualclassroom::messages.default_classroom_title');
    }
}
