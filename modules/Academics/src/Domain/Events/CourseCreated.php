<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Events;

use Shared\Domain\DomainEvent;

final class CourseCreated extends DomainEvent
{
    /**
     * @param  array<string, string>  $name
     */
    public function __construct(
        public readonly string $courseId,
        public readonly string $organizationId,
        public readonly string $levelId,
        public readonly string $code,
        public readonly array $name,
        public readonly ?int $totalSessions,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'academics.course_created';
    }

    public function module(): string
    {
        return 'Academics';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'course_id' => $this->courseId,
            'organization_id' => $this->organizationId,
            'level_id' => $this->levelId,
            'code' => $this->code,
            'name' => $this->name,
            'total_sessions' => $this->totalSessions,
        ];
    }
}
