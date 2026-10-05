<?php

declare(strict_types=1);

namespace App\Services\Console;

use Modules\Academics\Domain\Contracts\ProgramRulesQueries;
use Modules\Sessions\Domain\Contracts\SessionFactsQueries;
use Modules\Sessions\Domain\ValueObjects\SessionPayrollFacts;
use Modules\Staff\Domain\Contracts\TeacherRateResolver;

/**
 * «كم ستدفع هذه الحصة لو اعتُمدت الآن؟»
 *
 * نفس الحساب الذي سيجريه `RecordSessionPayrollEntry` وقت الاعتماد، معروضًا
 * قبله. الغرض مزدوج: تحذير من حصة بلا سعر — اعتمادها يُقفلها بلا أي قيدة
 * ويُظهر للمعلم صفرًا بلا سبب مفهوم — واقتراح مبلغ يملك المدير قبوله أو
 * استبداله بأجر يدوي لهذه الحصة وحدها.
 *
 * السعر يُحسب من الحصة **الأصلية** إن كانت هذه تعويضية، تمامًا كما يفعل
 * المستمع، وإلا لأظهرت كل تعويضية نقصًا كاذبًا.
 *
 * الصفوف تتكرر بشدة على نفس (معلم، مقرر، نوع، مدة، يوم) — جدول أسبوعي واحد
 * يولّد عشرات الحصص المتطابقة في هذه المفاتيح — فتُحفظ الإجابة لكل مفتاح.
 */
final class SessionRateSuggestion
{
    /** @var array<string, int|null> */
    private array $rateCache = [];

    public function __construct(
        private readonly SessionFactsQueries $facts,
        private readonly ProgramRulesQueries $programs,
        private readonly TeacherRateResolver $rates,
    ) {}

    /**
     * هل لهذه الحصة سعر مطبَّق أصلًا؟ `null` حين لا تُعرف حقائقها.
     *
     * لا يُستثنى هنا `payroll_exempt` ولا يُقرأ الأجر اليدوي: السؤال عن وجود
     * سعر في جدول الأسعار، وهو ما تحذّر منه شاشة الاعتماد.
     */
    public function hasApplicableRate(string $sessionId): ?bool
    {
        $facts = $this->facts->payrollFactsFor($sessionId);

        return $facts === null ? null : $this->resolveMinorUnits($facts) !== null;
    }

    /**
     * الأجر المتوقَّع بالوحدات الصغرى، أو `null` حين لا قيدة ستُنشأ أصلًا
     * (حصة معفاة أو بلا سعر) أو حين لا تُعرف حقائق الحصة.
     */
    public function minorUnitsFor(string $sessionId): ?int
    {
        $facts = $this->facts->payrollFactsFor($sessionId);

        if ($facts === null || $facts->payrollExempt) {
            return null;
        }

        return $facts->payrollRateOverrideMinorUnits ?? $this->resolveMinorUnits($facts);
    }

    /**
     * لماذا لا يُقبل أجر يدوي لهذه الحصة؟ `null` يعني أنه مقبول.
     *
     * الحالتان تُخفيان حقل المبلغ في الواجهة بدل عرضه ثم رفضه بعد الضغط:
     * الحصة المعفاة لا قيدة لها أصلًا، والتعويضية تُسوَّى بقيدة أصلها.
     */
    public function pricingNoteFor(string $sessionId): ?string
    {
        $facts = $this->facts->payrollFactsFor($sessionId);

        if ($facts === null) {
            return null;
        }

        if ($facts->payrollExempt) {
            return 'exempt';
        }

        return $facts->isMakeup() ? 'makeup' : null;
    }

    private function resolveMinorUnits(SessionPayrollFacts $facts): ?int
    {
        if ($facts->isMakeup() && $facts->makeupForSessionId !== null) {
            $facts = $this->facts->payrollFactsFor($facts->makeupForSessionId) ?? $facts;
        }

        $durationMinutes = (int) round($facts->scheduledStart->diffInMinutes($facts->scheduledEnd));

        $key = implode('|', [
            $facts->staffProfileId,
            $facts->courseId,
            $facts->sessionType,
            $durationMinutes,
            $facts->scheduledStart->toDateString(),
        ]);

        if (array_key_exists($key, $this->rateCache)) {
            return $this->rateCache[$key];
        }

        $programIds = $this->programs->programIdsOfCourse($facts->courseId);

        $resolved = $this->rates->resolve(
            staffProfileId: $facts->staffProfileId,
            sessionDate: $facts->scheduledStart,
            programId: $programIds === [] ? null : (string) reset($programIds),
            courseId: $facts->courseId,
            sessionType: $facts->sessionType,
            durationMinutes: $durationMinutes,
        );

        return $this->rateCache[$key] = $resolved === null ? null : $resolved['money']->minorUnits;
    }
}
