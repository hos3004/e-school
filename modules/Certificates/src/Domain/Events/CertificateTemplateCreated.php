<?php

declare(strict_types=1);

namespace Modules\Certificates\Domain\Events;

/**
 * أُنشئ قالب شهادة جديد.
 */
final class CertificateTemplateCreated extends CertificateEvent
{
    public function __construct(
        public readonly string $templateId,
        public readonly string $organizationId,
        public readonly ?string $programId,
        public readonly bool $isActive,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'certificates.template_created';
    }

    public function payload(): array
    {
        return [
            'template_id' => $this->templateId,
            'organization_id' => $this->organizationId,
            'program_id' => $this->programId,
            'is_active' => $this->isActive,
        ];
    }
}
