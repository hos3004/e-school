<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Certificates\Application\Actions\UpdateCertificateTemplateAction;
use Modules\Certificates\Domain\Models\CertificateTemplate;
use Modules\Certificates\Presentation\Http\Requests\UpdateCertificateTemplateRequest;
use Modules\Certificates\Presentation\Http\Resources\CertificateTemplateResource;

/**
 * تعديل قالب شهادة.
 */
final class UpdateCertificateTemplateController extends Controller
{
    public function __construct(
        private readonly UpdateCertificateTemplateAction $action,
    ) {}

    public function __invoke(UpdateCertificateTemplateRequest $request, string $template): CertificateTemplateResource
    {
        $templateModel = CertificateTemplate::query()->withTrashed()->findOrFail($template);

        Gate::authorize('update', $templateModel);

        $this->action->execute($templateModel, $request->validated());

        return new CertificateTemplateResource($templateModel->refresh());
    }
}
