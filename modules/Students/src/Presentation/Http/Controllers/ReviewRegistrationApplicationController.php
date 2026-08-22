<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Students\Application\Actions\ReviewRegistrationApplicationAction;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Presentation\Http\Requests\ReviewRegistrationApplicationRequest;
use Modules\Students\Presentation\Http\Resources\RegistrationApplicationResource;

final class ReviewRegistrationApplicationController extends Controller
{
    public function __construct(private readonly ReviewRegistrationApplicationAction $action) {}

    public function __invoke(
        ReviewRegistrationApplicationRequest $request,
        RegistrationApplication $registrationApplication,
    ): RegistrationApplicationResource {
        return RegistrationApplicationResource::make($this->action->execute(
            $registrationApplication,
            (string) $request->user()->getAuthIdentifier(),
        ));
    }
}
