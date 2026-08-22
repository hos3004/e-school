<?php

declare(strict_types=1);

namespace Modules\Certificates\Domain\Events;

/**
 * سُحبت شهادة صادرة — لا حذف للسجل، فقط تعليق موثق بالسبب.
 */
final class CertificateRevoked extends CertificateEvent
{
    public function __construct(
        public readonly string $certificateId,
        public readonly string $organizationId,
        public readonly string $studentProfileId,
        public readonly string $serialNumber,
        public readonly string $revokedAt,
        public readonly ?string $revokedById,
        public readonly string $reason,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'certificates.revoked';
    }

    public function payload(): array
    {
        return [
            'certificate_id' => $this->certificateId,
            'organization_id' => $this->organizationId,
            'student_profile_id' => $this->studentProfileId,
            'serial_number' => $this->serialNumber,
            'revoked_at' => $this->revokedAt,
            'revoked_by_id' => $this->revokedById,
            'reason' => $this->reason,
        ];
    }
}
