<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Students\Application\Actions\AcceptRegistrationApplicationAction;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Presentation\Http\Requests\AcceptRegistrationApplicationRequest;
use Modules\Students\Presentation\Http\Resources\RegistrationApplicationResource;

final class AcceptRegistrationApplicationController extends Controller
{
    public function __construct(private readonly AcceptRegistrationApplicationAction $action) {}

    public function __invoke(
        AcceptRegistrationApplicationRequest $request,
        RegistrationApplication $registrationApplication,
    ): RegistrationApplicationResource {
        return RegistrationApplicationResource::make($this->action->execute(
            $registrationApplication,
            (string) $request->user()->getAuthIdentifier(),
            (string) $request->validated('reason'),
        ));
    }
}
