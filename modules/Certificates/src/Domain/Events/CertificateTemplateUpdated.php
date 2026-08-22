<?php

declare(strict_types=1);

namespace Modules\Certificates\Domain\Events;

/**
 * عُدّل قالب شهادة قائم — تصميم أو تفعيل أو تعطيل.
 */
final class CertificateTemplateUpdated extends CertificateEvent
{
    /**
     * @param array<string, mixed> $changes الحقول المتغيرة: قيمة قبلية => بعدية
     */
    public function __construct(
        public readonly string $templateId,
        public readonly string $organizationId,
        public readonly bool $isActive,
        public readonly array $changes,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'certificates.template_updated';
    }

    public function payload(): array
    {
        return [
            'template_id' => $this->templateId,
            'organization_id' => $this->organizationId,
            'is_active' => $this->isActive,
            'changes' => $this->changes,
        ];
    }
}
