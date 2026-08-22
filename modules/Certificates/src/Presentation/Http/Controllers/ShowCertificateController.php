<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Certificates\Domain\Models\Certificate;
use Modules\Certificates\Presentation\Http\Resources\CertificateResource;

/**
 * عرض شهادة واحدة.
 */
final class ShowCertificateController extends Controller
{
    public function __invoke(string $certificate): CertificateResource
    {
        $certificateModel = Certificate::query()->withTrashed()->findOrFail($certificate);

        Gate::authorize('view', $certificateModel);

        return new CertificateResource($certificateModel);
    }
}
