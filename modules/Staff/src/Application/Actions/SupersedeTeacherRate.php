<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Staff\Domain\Enums\RateScope;
use Modules\Staff\Domain\Models\TeacherContract;
use Modules\Staff\Domain\Models\TeacherRate;
use Shared\Support\BusinessRuleViolation;
use Shared\ValueObjects\Money;

/**
 * تغيير سعر حصة المعلم من تاريخ محدد.
 *
 * `AddTeacherRate` وحده يرفض أي سعر جديد لنطاق له سعر مفتوح النهاية، فكان
 * السعر يُدخَل مرة عند إنشاء الملف ولا يُعدَّل أبدًا. التغيير هنا زمني لا
 * تحريري: يُقفل السعر الساري عند تاريخ السريان الجديد ثم يُضاف السعر الجديد
 * بعده، فتبقى قيمة كل حصة هي السعر الساري بتاريخها.
 *
 * لا يُمس مبلغ سعر قائم ولا تُحذف سطوره، ولا تتغير قيود الدفتر: القيدة تحفظ
 * سعرها وقت الحصة، فالحصص الماضية والمدفوعة تبقى كما احتُسبت.
 */
final readonly class SupersedeTeacherRate
{
    public function __construct(
        private AddTeacherRate $addRate,
        private AuditRecorder $audit,
    ) {}

    /** المبلغ بالوحدة الرئيسية؛ العملة من العقد الساري لا من المستدعي. */
    public function execute(
        string $staffProfileId,
        RateScope $scope,
        string $amountMajor,
        CarbonImmutable|string $effectiveFrom,
        ?string $programId = null,
        ?string $courseId = null,
        ?string $sessionType = null,
        ?string $actorId = null,
        ?string $reason = null,
    ): TeacherRate {
        $from = $effectiveFrom instanceof CarbonImmutable ? $effectiveFrom : CarbonImmutable::parse($effectiveFrom);

        /** @var TeacherContract|null $contract */
        $contract = TeacherContract::query()
            ->forProfile($staffProfileId)
            ->activeOn($from)
            ->orderByDesc('effective_from')
            ->first();

        if ($contract === null) {
            throw BusinessRuleViolation::make(
                'staff.rate_contract_missing',
                'staff::errors.rate_contract_missing',
            );
        }

        $amount = Money::fromMajor(
            $amountMajor,
            (string) ($contract->currency ?? config('staff.currency.default', 'EGP')),
        );

        return DB::transaction(function () use ($contract, $scope, $amount, $from, $programId, $courseId, $sessionType, $actorId, $reason): TeacherRate {
            $current = $this->latestRate($contract->id, $scope, $programId, $courseId, $sessionType);

            $previousEnd = $current?->effective_to;

            if ($current !== null) {
                /*
                 * فحص التقاطع في `AddTeacherRate` لا يرى سعرًا يبدأ بعد تاريخ
                 * السريان الجديد، فكان التسعير بأثر رجعي ينشئ سعرين مفتوحين
                 * لنفس النطاق. السعر التالي يبدأ بعد الساري لا قبله.
                 */
                if (CarbonImmutable::instance($current->effective_from)->startOfDay()->gt($from->startOfDay())) {
                    throw BusinessRuleViolation::make(
                        'staff.rate_effective_before_current',
                        'staff::errors.rate_effective_before_current',
                    );
                }

                if ($previousEnd !== null
                    && CarbonImmutable::instance($previousEnd)->startOfDay()->lte($from->startOfDay())) {
                    // سعر منتهٍ قبل تاريخ السريان الجديد: لا شيء يُقفل.
                    $current = null;
                }
            }

            if ($current !== null) {
                $current->effective_to = $from;
                $current->save();

                $this->audit->record(
                    organizationId: (string) $contract->organization_id,
                    actorId: $actorId,
                    actorType: $actorId === null ? 'system' : 'user',
                    action: 'staff.rate_superseded',
                    auditableType: 'teacher_rate',
                    auditableId: (string) $current->getKey(),
                    oldValues: ['effective_to' => $previousEnd?->toDateString()],
                    newValues: ['effective_to' => $from->toDateString()],
                    reason: $reason === null || trim($reason) === '' ? null : trim($reason),
                );
            }

            return $this->addRate->execute(
                contract: $contract,
                scope: $scope,
                amount: $amount,
                effectiveFrom: $from,
                effectiveTo: null,
                programId: $programId,
                courseId: $courseId,
                sessionType: $sessionType,
                actorId: $actorId,
                reason: $reason,
            );
        });
    }

    /** آخر سعر مسجَّل لنفس النطاق ومفاتيحه — بنفس مفاتيح حلّ السعر في العقد. */
    private function latestRate(
        string $contractId,
        RateScope $scope,
        ?string $programId,
        ?string $courseId,
        ?string $sessionType,
    ): ?TeacherRate {
        /** @var TeacherRate|null $rate */
        $rate = TeacherRate::query()
            ->forContract($contractId)
            ->where('scope', $scope)
            ->where(
                fn (Builder $query): Builder => $query
                    ->when($programId === null, fn (Builder $q): Builder => $q->whereNull('program_id'))
                    ->when($programId !== null, fn (Builder $q): Builder => $q->where('program_id', $programId))
                    ->when($courseId === null, fn (Builder $q): Builder => $q->whereNull('course_id'))
                    ->when($courseId !== null, fn (Builder $q): Builder => $q->where('course_id', $courseId))
                    ->when($sessionType === null, fn (Builder $q): Builder => $q->whereNull('session_type'))
                    ->when($sessionType !== null, fn (Builder $q): Builder => $q->where('session_type', $sessionType)),
            )
            ->orderByDesc('effective_from')
            ->first();

        return $rate;
    }
}
