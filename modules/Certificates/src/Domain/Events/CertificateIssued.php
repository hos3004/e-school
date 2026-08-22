<?php

declare(strict_types=1);

namespace Modules\Certificates\Domain\Events;

/**
 * صُدرت شهادة لطالب — اللحظة المرجعية للسجل الأكاديمي.
 */
final class CertificateIssued extends CertificateEvent
{
    public function __construct(
        public readonly string $certificateId,
        public readonly string $organizationId,
        public readonly string $studentProfileId,
        public readonly ?string $templateId,
        public readonly ?string $programId,
        public readonly ?string $enrollmentId,
        public readonly string $serialNumber,
        public readonly string $issuedAt,
        public readonly ?string $expiresAt,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'certificates.issued';
    }

    public function payload(): array
    {
        return [
            'certificate_id' => $this->certificateId,
            'organization_id' => $this->organizationId,
            'student_profile_id' => $this->studentProfileId,
            'template_id' => $this->templateId,
            'program_id' => $this->programId,
            'enrollment_id' => $this->enrollmentId,
            'serial_number' => $this->serialNumber,
            'issued_at' => $this->issuedAt,
            'expires_at' => $this->expiresAt,
        ];
    }
}
