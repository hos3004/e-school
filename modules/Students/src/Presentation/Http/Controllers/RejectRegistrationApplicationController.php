<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Students\Application\Actions\RejectRegistrationApplicationAction;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Presentation\Http\Requests\RejectRegistrationApplicationRequest;
use Modules\Students\Presentation\Http\Resources\RegistrationApplicationResource;

final class RejectRegistrationApplicationController extends Controller
{
    public function __construct(private readonly RejectRegistrationApplicationAction $action) {}

    public function __invoke(
        RejectRegistrationApplicationRequest $request,
        RegistrationApplication $registrationApplication,
    ): RegistrationApplicationResource {
        return RegistrationApplicationResource::make($this->action->execute(
            $registrationApplication,
            (string) $request->validated('reason', ''),
            (string) $request->user()->getAuthIdentifier(),
        ));
    }
}
