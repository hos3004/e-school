<?php

declare(strict_types=1);

namespace Modules\Certificates\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Certificates\Domain\Events\CertificateTemplateCreated;
use Modules\Certificates\Domain\Models\CertificateTemplate;
use Shared\Support\Transaction;

/**
 * إنشاء قالب شهادة للمؤسسة — عام لكل البرامج أو مخصص لبرنامج واحد.
 */
final readonly class CreateCertificateTemplateAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?string $actorId = null): CertificateTemplate
    {
        /** @var array{0: CertificateTemplate, 1: CertificateTemplateCreated} $result */
        $result = $this->transaction->run(function () use ($data, $actorId): array {
            $template = new CertificateTemplate;
            $template->fill([
                ...$data,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);
            $template->save();

            return [$template, new CertificateTemplateCreated(
                templateId: (string) $template->getKey(),
                organizationId: $template->organization_id,
                programId: $template->program_id,
                isActive: $template->is_active,
                actorId: $actorId,
            )];
        });

        [$template, $event] = $result;

        $this->events->dispatch($event);

        return $template;
    }
}
