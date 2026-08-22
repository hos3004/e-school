<?php

declare(strict_types=1);

namespace Modules\Certificates\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use Modules\Certificates\Domain\Events\CertificateIssued;
use Modules\Certificates\Domain\Models\Certificate;
use Modules\Certificates\Domain\Models\CertificateTemplate;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إصدار شهادة لطالب.
 *
 * قواعد العمل:
 *  - القالب إن حُدد يجب أن يخص نفس المؤسسة وأن يكون مفعّلًا.
 *  - تاريخ الانتهاء، إن قُدّم، لازم يكون بعد تاريخ الإصدار.
 *  - الرقم التسلسلي يُولَّد آليًا وفق نمط الإعدادات — لا إدخال بشري له.
 */
final readonly class IssueCertificateAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function execute(array $data, ?string $actorId = null): Certificate
    {
        $issuedAt = isset($data['issued_at'])
            ? CarbonImmutable::parse($data['issued_at'], 'UTC')
            : CarbonImmutable::now('UTC');

        if (isset($data['expires_at'])) {
            $expiresAt = CarbonImmutable::parse($data['expires_at'], 'UTC');

            if ($expiresAt->lessThanOrEqualTo($issuedAt)) {
                throw BusinessRuleViolation::make(
                    'certificates.expiry_before_issue',
                    'certificates::errors.expiry_before_issue',
                );
            }
        } else {
            // مدة الصلاحية الافتراضية سياسة مؤسسية — من الإعدادات لا الكود.
            $validityYears = (int) config('certificates.validity.default_years', 0);

            $expiresAt = $validityYears > 0
                ? $issuedAt->addYears($validityYears)
                : null;
        }

        /** @var CertificateTemplate|null $template */
        $template = isset($data['certificate_template_id'])
            ? CertificateTemplate::query()->find($data['certificate_template_id'])
            : null;

        if ($template !== null) {
            if ($template->organization_id !== $data['organization_id']) {
                throw BusinessRuleViolation::make(
                    'certificates.template_foreign_organization',
                    'certificates::errors.template_foreign_organization',
                );
            }

            if (!$template->is_active) {
                throw BusinessRuleViolation::make(
                    'certificates.template_inactive',
                    'certificates::errors.template_inactive',
                );
            }
        }

        [$certificate, $event] = $this->transaction->run(function () use ($data, $template, $issuedAt, $expiresAt, $actorId): array {
            $certificate = new Certificate;
            $certificate->fill([
                ...$data,
                'certificate_template_id' => $template?->getKey(),
                'serial_number' => $this->nextSerialNumber($issuedAt),
                'title' => $data['title'],
                'issued_at' => $issuedAt,
                'expires_at' => $expiresAt,
                'issued_by' => $data['issued_by'] ?? (string) auth()->id(),
            ]);
            $certificate->save();

            return [$certificate, new CertificateIssued(
                certificateId: (string) $certificate->getKey(),
                organizationId: $certificate->organization_id,
                studentProfileId: $certificate->student_profile_id,
                templateId: $certificate->certificate_template_id,
                programId: $certificate->program_id,
                enrollmentId: $certificate->enrollment_id,
                serialNumber: $certificate->serial_number,
                issuedAt: $issuedAt->toIso8601String(),
                expiresAt: $expiresAt?->toIso8601String(),
                actorId: $actorId,
            )];
        });

        $this->events->dispatch($event);

        return $certificate;
    }

    /**
     * الرقم التسلسلي فريد بالبناء: بادئة + سنة + ULID.
     */
    private function nextSerialNumber(CarbonImmutable $issuedAt): string
    {
        $prefix = (string) config('certificates.serial.prefix', 'CERT');

        return sprintf('%s-%s-%s', strtoupper($prefix), $issuedAt->format('Y'), (string) Str::ulid());
    }
}
