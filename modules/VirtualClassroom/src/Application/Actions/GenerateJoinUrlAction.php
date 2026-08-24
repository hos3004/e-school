<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Application\Actions;

use Modules\VirtualClassroom\Domain\Contracts\VirtualClassroomProvider;
use Modules\VirtualClassroom\Domain\Enums\JoinRole;
use Modules\VirtualClassroom\Domain\Exceptions\ClassroomProviderException;
use Modules\VirtualClassroom\Domain\Models\Classroom;
use Modules\VirtualClassroom\Domain\ValueObjects\JoinRequest;
use Shared\Support\BusinessRuleViolation;

final readonly class GenerateJoinUrlAction
{
    public function __construct(
        private VirtualClassroomProvider $provider,
    ) {}

    public function execute(
        Classroom $classroom,
        string $userId,
        string $displayName,
        JoinRole $role,
        bool $isFrozen = false,
    ): string {
        if ($isFrozen) {
            throw BusinessRuleViolation::make(
                'virtualclassroom.student_frozen',
                'virtualclassroom::errors.student_frozen_cannot_join',
            );
        }

        $password = match ($role) {
            JoinRole::Moderator => $classroom->moderator_secret,
            JoinRole::Viewer => $classroom->attendee_secret,
        };

        if ($password === null || $password === '') {
            throw ClassroomProviderException::rejected([
                'action' => 'join',
                'reason' => 'missing_classroom_secrets',
            ]);
        }

        $request = new JoinRequest(
            externalId: $classroom->external_id,
            displayName: $displayName,
            role: $role,
            rolePassword: $password,
            externalUserId: $userId,
        );

        return $this->provider->generateJoinUrl($request);
    }
}
