<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Students\Application\Actions\SubmitRegistrationApplicationAction;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Presentation\Http\Requests\SubmitRegistrationApplicationRequest;
use Modules\Students\Presentation\Http\Resources\RegistrationApplicationResource;

final class SubmitRegistrationApplicationController extends Controller
{
    public function __construct(private readonly SubmitRegistrationApplicationAction $action) {}

    public function __invoke(
        SubmitRegistrationApplicationRequest $request,
        RegistrationApplication $registrationApplication,
    ): RegistrationApplicationResource {
        return RegistrationApplicationResource::make(
            $this->action->execute($registrationApplication),
        );
    }
}
