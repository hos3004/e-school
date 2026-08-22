<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Certificates\Application\Actions\RevokeCertificateAction;
use Modules\Certificates\Domain\Models\Certificate;
use Modules\Certificates\Presentation\Http\Requests\RevokeCertificateRequest;
use Modules\Certificates\Presentation\Http\Resources\CertificateResource;

/**
 * سحب شهادة صادرة.
 */
final class RevokeCertificateController extends Controller
{
    public function __construct(
        private readonly RevokeCertificateAction $action,
    ) {}

    public function __invoke(RevokeCertificateRequest $request, string $certificate): CertificateResource
    {
        $certificateModel = Certificate::query()->findOrFail($certificate);

        Gate::authorize('revoke', $certificateModel);

        $this->action->execute($certificateModel, (string) $request->validated('reason'));

        return new CertificateResource($certificateModel->refresh());
    }
}
