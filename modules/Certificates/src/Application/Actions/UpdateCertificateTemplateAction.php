<?php

declare(strict_types=1);

namespace Modules\Certificates\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Certificates\Domain\Events\CertificateTemplateUpdated;
use Modules\Certificates\Domain\Models\CertificateTemplate;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تعديل قالب شهادة قائم — التصميم أو الاسم أو حالة التفعيل.
 *
 * القوالب المعطلة تبقى مرجعًا للشهادات الصادرة بها سابقًا؛ التعطيل
 * يمنع الإصدار الجديد فقط.
 */
final readonly class UpdateCertificateTemplateAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param array<string, mixed> $data الحقول المسموح تعديلها فقط
     */
    public function execute(CertificateTemplate $template, array $data, ?string $actorId = null): CertificateTemplate
    {
        $allowed = array_intersect_key($data, array_flip([
            'program_id',
            'name',
            'layout',
            'background_image_path',
            'is_active',
        ]));

        if ($allowed === []) {
            throw BusinessRuleViolation::make(
                'certificates.template_no_changes',
                'certificates::errors.template_no_changes',
            );
        }

        /** @var array{0: CertificateTemplate, 1: CertificateTemplateUpdated} $result */
        $result = $this->transaction->run(function () use ($template, $allowed, $actorId): array {
            $template->fill($allowed)->save();

            return [$template, new CertificateTemplateUpdated(
                templateId: (string) $template->getKey(),
                organizationId: $template->organization_id,
                isActive: $template->is_active,
                changes: $this->diff($allowed),
                actorId: $actorId,
            )];
        });

        [$template, $event] = $result;

        $this->events->dispatch($event);

        return $template;
    }

    /**
     * حمولة الحدث قيم بدائية فقط — المصفوفات تُرمَّز JSON.
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function diff(array $data): array
    {
        $changes = [];

        foreach ($data as $field => $value) {
            $changes[$field] = is_scalar($value) || $value === null
                ? (string) json_encode($value)
                : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $changes;
    }
}
