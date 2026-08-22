<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Certificates\Application\Actions\IssueCertificateAction;
use Modules\Certificates\Presentation\Http\Requests\IssueCertificateRequest;
use Modules\Certificates\Presentation\Http\Resources\CertificateResource;

/**
 * إصدار شهادة لطالب.
 */
final class IssueCertificateController extends Controller
{
    public function __construct(
        private readonly IssueCertificateAction $action,
    ) {}

    public function __invoke(IssueCertificateRequest $request): CertificateResource
    {
        $certificate = $this->action->execute($request->validated());

        return new CertificateResource($certificate);
    }
}
