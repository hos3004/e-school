<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Contracts;

use Carbon\CarbonImmutable;
use Modules\Staff\Domain\Enums\RateScope;
use Shared\ValueObjects\Money;

/**
 * العقد العام الذي يستعمله موديول Payroll لحلّ سعر المعلم بتاريخ الحصة.
 *
 * يُنفَّذ داخل Staff وحده — المستهلك لا يعرف جداول هذا الموديول.
 */
interface TeacherRateResolver
{
    /**
     * يحلّ سعر الحصة بأعلى تخصّص متاح: course ← program ← session_type ← default.
     *
     * يُعتمد السعر الساري **بتاريخ الحصة** لا السعر الحالي.
     *
     * `contract_id` جزء من الإجابة لأن قيدة الدفتر تُنسب إلى العقد الساري وقت
     * الحصة، ولا يجوز للمستهلك أن يستنتجه بقراءة جداول هذا الموديول.
     *
     * @return array{money: Money, scope: RateScope, rate_id: string, contract_id: string, contract_basis: string}|null
     */
    public function resolve(
        string $staffProfileId,
        CarbonImmutable $sessionDate,
        ?string $programId = null,
        ?string $courseId = null,
        ?string $sessionType = null,
    ): ?array;
}
