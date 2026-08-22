<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Listeners;

use Modules\Reporting\Application\Actions\IngestDomainEventAction;
use Modules\Reporting\Application\Actions\UpdateStudentDashboardAction;
use Modules\Reporting\Application\Actions\UpdateTeacherDashboardAction;
use Shared\Domain\DomainEvent;

/**
 * مستمع الإسقاط العام — يُسجَّل على أحداث الموديولات الأخرى.
 *
 * يعمل على مرحلتين:
 *  1. إدخال الحدث في سجل Reporting — إن كان مُدخلاً سابقًا يتوقف فورًا
 *     (idempotency: الحدث نفسه لا يُسقط مرتين).
 *  2. توجيه التحديثات حسب خريطة config('reporting.projections') المفتاح
 *     فيها اسم الحدث المستقر — بلا استيراد أي نموذج أو صنف من موديول آخر.
 */
final readonly class ProjectDomainEventToDashboards
{
    public function __construct(
        private IngestDomainEventAction $ingest,
        private UpdateStudentDashboardAction $studentProjection,
        private UpdateTeacherDashboardAction $teacherProjection,
    ) {}

    public function handle(DomainEvent $event): void
    {
        if (!$this->ingest->execute($event)) {
            return;
        }

        $payload = $event->payload();

        // الخريطة تُقرأ ككتلة واحدة لأن مفاتيحها تحمل نقاطًا داخلها
        // ('sessions.completed') ولا يصح الوصول إليها بمسار منقّط.
        /** @var array<string, list<array<string, mixed>>> $map */
        $map = (array) config('reporting.projections', []);

        foreach ($map[$event->name()] ?? [] as $spec) {
            if (!is_array($spec)) {
                continue;
            }

            $delta = $this->buildDelta($spec, $payload);

            match ($spec['board'] ?? null) {
                'student' => $this->applyStudent($delta),
                'teacher' => $this->teacherProjection->execute($delta),
                default => null,
            };
        }
    }

    /**
     * @param array<string, mixed> $spec
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function buildDelta(array $spec, array $payload): array
    {
        $delta = [
            'metric' => (string) ($spec['metric'] ?? ''),
        ];

        // معرّف المؤسسة موجود في كل الحمولات — يُنسخ تلقائيًا.
        if (isset($payload['organization_id'])) {
            $delta['organization_id'] = $payload['organization_id'];
        }

        foreach ((array) ($spec['keys'] ?? []) as $field) {
            if (isset($payload[$field])) {
                $delta[$field] = $payload[$field];
            }
        }

        $atField = $spec['at'] ?? null;

        if (is_string($atField) && isset($payload[$atField])) {
            $delta['at'] = $payload[$atField];
        }

        $amountField = $spec['amount_minor'] ?? null;

        if (is_string($amountField) && isset($payload[$amountField])) {
            $delta['amount_minor'] = (int) $payload[$amountField];
        }

        return $delta;
    }

    /**
     * @param array<string, mixed> $delta
     */
    private function applyStudent(array $delta): void
    {
        if (!isset($delta['enrollment_id'], $delta['student_profile_id'])) {
            return; // الحمولة لا تحمل مفاتيح اللوحة — نتجاهل بهدوء.
        }

        $this->studentProjection->execute($delta);
    }
}
