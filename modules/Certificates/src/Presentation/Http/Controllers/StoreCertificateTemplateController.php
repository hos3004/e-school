<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Certificates\Application\Actions\CreateCertificateTemplateAction;
use Modules\Certificates\Presentation\Http\Requests\StoreCertificateTemplateRequest;
use Modules\Certificates\Presentation\Http\Resources\CertificateTemplateResource;

/**
 * إنشاء قالب شهادة.
 */
final class StoreCertificateTemplateController extends Controller
{
    public function __construct(
        private readonly CreateCertificateTemplateAction $action,
    ) {}

    public function __invoke(StoreCertificateTemplateRequest $request): CertificateTemplateResource
    {
        $template = $this->action->execute($request->validated());

        return new CertificateTemplateResource($template);
    }
}
