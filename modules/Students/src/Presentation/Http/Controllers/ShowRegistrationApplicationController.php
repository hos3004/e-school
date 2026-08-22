<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Presentation\Http\Resources\RegistrationApplicationResource;

final class ShowRegistrationApplicationController extends Controller
{
    public function __invoke(RegistrationApplication $registrationApplication): RegistrationApplicationResource
    {
        Gate::authorize('view', $registrationApplication);

        return RegistrationApplicationResource::make($registrationApplication);
    }
}
