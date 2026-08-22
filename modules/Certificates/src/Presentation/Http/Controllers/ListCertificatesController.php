<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Certificates\Domain\Models\Certificate;
use Modules\Certificates\Presentation\Http\Resources\CertificateResource;

/**
 * قائمة شهادات المؤسسة الحالية.
 */
final class ListCertificatesController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        $certificates = Certificate::query()
            ->withTrashed()
            ->forOrganization((string) auth()->user()->organization_id)
            ->orderByDesc('issued_at')
            ->paginate(20);

        return CertificateResource::collection($certificates);
    }
}
