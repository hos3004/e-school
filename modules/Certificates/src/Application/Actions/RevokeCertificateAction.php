<?php

declare(strict_types=1);

namespace Modules\Certificates\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Certificates\Domain\Events\CertificateRevoked;
use Modules\Certificates\Domain\Models\Certificate;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * سحب شهادة صادرة — تعليق موثق لا حذف.
 *
 * السجل يبقى محفوظًا (SoftDeletes) مع سبب السحب وهوية الساحِب داخل
 * metadata، التزامًا بقاعدة «لا حذف — تعليق فقط».
 */
final readonly class RevokeCertificateAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(Certificate $certificate, string $reason, ?string $actorId = null): Certificate
    {
        if ($certificate->trashed()) {
            throw BusinessRuleViolation::make(
                'certificates.already_revoked',
                'certificates::errors.already_revoked',
            );
        }

        $revokedAt = CarbonImmutable::now('UTC');
        $revokedById = $actorId ?? (string) auth()->id();

        /** @var array{0: Certificate, 1: CertificateRevoked} $result */
        $result = $this->transaction->run(function () use ($certificate, $reason, $revokedAt, $revokedById, $actorId): array {
            $metadata = $certificate->metadata ?? [];
            $metadata['revocation'] = [
                'reason' => $reason,
                'revoked_by' => $revokedById,
                'revoked_at' => $revokedAt->toIso8601String(),
            ];

            $certificate->fill(['metadata' => $metadata])->save();
            $certificate->delete();

            return [$certificate, new CertificateRevoked(
                certificateId: (string) $certificate->getKey(),
                organizationId: $certificate->organization_id,
                studentProfileId: $certificate->student_profile_id,
                serialNumber: $certificate->serial_number,
                revokedAt: $revokedAt->toIso8601String(),
                revokedById: $revokedById,
                reason: $reason,
                actorId: $actorId,
            )];
        });

        [$certificate, $event] = $result;

        $this->events->dispatch($event);

        return $certificate;
    }
}
