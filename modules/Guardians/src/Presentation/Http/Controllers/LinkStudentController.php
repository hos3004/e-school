<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Guardians\Application\Actions\LinkStudentToGuardian;
use Modules\Guardians\Presentation\Http\Requests\LinkStudentRequest;
use Modules\Guardians\Presentation\Http\Resources\GuardianLinkResource;

final class LinkStudentController
{
    public function __construct(
        private readonly LinkStudentToGuardian $action,
    ) {}

    public function __invoke(LinkStudentRequest $request, string $guardianProfile): JsonResponse
    {
        $link = $this->action->execute(
            $guardianProfile,
            (string) $request->validated('student_profile_id'),
            $request->validated(),
        );

        return GuardianLinkResource::make($link)
            ->response()
            ->setStatusCode(201);
    }
}
