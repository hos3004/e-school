<?php

declare(strict_types=1);

namespace Modules\Students\Application\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\RegistrationQuestion;
use Modules\Students\Domain\ValueObjects\FilterableQuestionData;

/**
 * فلاتر شاشة طلبات التسجيل — كل الاستعلامات داخل جداول موديول Students.
 *
 * العمر لا يُخزَّن ولا يُحسب في SQL بدالة عمر: يُترجم نطاق العمر إلى نطاق
 * `date_of_birth` قبل الاستعلام، فيبقى الشرط قابلًا لاستعمال الفهرس ولا
 * يتحول إلى مسح كامل للجدول.
 *
 * إجابات الأسئلة الديناميكية تُستعلم بعامل الاحتواء `@>` في PostgreSQL
 * (يستفيد من فهرس GIN) للقيم المحددة، وبـ `jsonb_array_elements` للنطاقات
 * الرقمية. لا دوال خاصة بـ MySQL في أي منهما.
 */
final readonly class RegistrationApplicationFilterService
{
    /**
     * نطاق العمر → نطاق تاريخ ميلاد.
     *
     * من كان عمره `min` سنة على الأكثر وُلد في أو قبل (اليوم − min سنة)، ومن
     * كان عمره `max` سنة على الأكثر وُلد بعد (اليوم − (max+1) سنة). التاريخ
     * المرجعي هو «اليوم» بتوقيت المستخدم، لأن العمر مفهوم مدني محلي، ثم
     * تُقارَن النتيجة بعمود `date` مخزَّن بلا منطقة زمنية.
     *
     * @param Builder<RegistrationApplication> $query
     * @return Builder<RegistrationApplication>
     */
    public function applyAgeRange(
        Builder $query,
        ?int $minimumAge,
        ?int $maximumAge,
        string $viewerTimezone,
    ): Builder {
        $today = CarbonImmutable::now($viewerTimezone)->startOfDay();

        if ($minimumAge !== null) {
            $query->whereDate('date_of_birth', '<=', $today->subYears($minimumAge)->toDateString());
        }

        if ($maximumAge !== null) {
            $query->whereDate('date_of_birth', '>', $today->subYears($maximumAge + 1)->toDateString());
        }

        return $query;
    }

    /**
     * فلتر اللغة عبر ملف الطالب المرتبط.
     *
     * `registration_applications` لا يحمل عمود لغة؛ اللغة المفضّلة تعيش في
     * `student_profiles` وهو جدول الموديول نفسه. الطلبات التي بلغت
     * `waiting_assignment` أو `assigned` تملك ملف طالب دائمًا بحكم قيد
     * `registration_applications_assignment_clearance_check`، وهي وحدها
     * المستهدفة بالتسكين — فالفلتر يغطي جمهور هذا التدفق كاملًا.
     *
     * @param Builder<RegistrationApplication> $query
     * @param list<string> $locales
     * @return Builder<RegistrationApplication>
     */
    public function applyLanguage(Builder $query, array $locales): Builder
    {
        $locales = array_values(array_filter(
            $locales,
            static fn (mixed $locale): bool => is_string($locale) && $locale !== '',
        ));

        if ($locales === []) {
            return $query;
        }

        return $query->whereHas(
            'studentProfile',
            static fn (Builder $profile): Builder => $profile->whereIn('preferred_language', $locales),
        );
    }

    /**
     * نطاق تاريخ التسجيل.
     *
     * القيم تصل من الواجهة بتوقيت المستخدم وتُحوَّل إلى UTC قبل المقارنة، لأن
     * `submitted_at` و`created_at` مخزّنان UTC.
     *
     * @param Builder<RegistrationApplication> $query
     * @return Builder<RegistrationApplication>
     */
    public function applySubmissionDateRange(
        Builder $query,
        ?string $from,
        ?string $until,
        string $viewerTimezone,
    ): Builder {
        if ($from !== null && $from !== '') {
            $query->where(
                'created_at',
                '>=',
                CarbonImmutable::parse($from, $viewerTimezone)->startOfDay()->utc(),
            );
        }

        if ($until !== null && $until !== '') {
            $query->where(
                'created_at',
                '<=',
                CarbonImmutable::parse($until, $viewerTimezone)->endOfDay()->utc(),
            );
        }

        return $query;
    }

    /**
     * الأسئلة المسموح بالفلترة بها في هذه المؤسسة.
     *
     * @return list<FilterableQuestionData>
     */
    public function filterableQuestions(string $organizationId): array
    {
        if ($organizationId === '') {
            return [];
        }

        return RegistrationQuestion::query()
            ->forOrganization($organizationId)
            ->filterable()
            ->get()
            ->map(static fn (RegistrationQuestion $question): FilterableQuestionData => new FilterableQuestionData(
                id: (string) $question->getKey(),
                label: $question->localizedQuestion(),
                type: $question->type,
                options: array_values(array_filter(
                    $question->options ?? [],
                    static fn (mixed $option): bool => is_string($option) && $option !== '',
                )),
            ))
            ->values()
            ->all();
    }

    /**
     * فلترة بإجابة سؤال اختيار — مطابقة تامة عبر عامل الاحتواء.
     *
     * @param Builder<RegistrationApplication> $query
     * @param list<string> $answers
     * @return Builder<RegistrationApplication>
     */
    public function applySelectAnswer(Builder $query, string $questionId, array $answers): Builder
    {
        $answers = array_values(array_filter(
            $answers,
            static fn (mixed $answer): bool => is_string($answer) && $answer !== '',
        ));

        if ($answers === []) {
            return $query;
        }

        return $query->where(static function (Builder $nested) use ($questionId, $answers): void {
            foreach ($answers as $answer) {
                $nested->orWhereRaw(
                    'evaluation_answers @> ?::jsonb',
                    [json_encode(
                        [['question_id' => $questionId, 'answer' => $answer]],
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                    )],
                );
            }
        });
    }

    /**
     * فلترة بإجابة سؤال رقمي — نطاق من/إلى.
     *
     * الإجابة مخزَّنة نصًا داخل اللقطة، فيُشترط أن تكون رقمًا صالحًا قبل
     * التحويل حتى لا يفشل الاستعلام على إجابة قديمة غير رقمية.
     *
     * @param Builder<RegistrationApplication> $query
     * @return Builder<RegistrationApplication>
     */
    public function applyNumberAnswerRange(
        Builder $query,
        string $questionId,
        ?float $from,
        ?float $until,
    ): Builder {
        if ($from === null && $until === null) {
            return $query;
        }

        $conditions = ['answer.question_id = ?', "answer.answer ~ '^-?[0-9]+(\\.[0-9]+)?$'"];
        $bindings = [$questionId];

        if ($from !== null) {
            $conditions[] = '(answer.answer)::numeric >= ?';
            $bindings[] = $from;
        }

        if ($until !== null) {
            $conditions[] = '(answer.answer)::numeric <= ?';
            $bindings[] = $until;
        }

        $where = implode(' AND ', $conditions);

        return $query->whereRaw(
            <<<SQL
                EXISTS (
                    SELECT 1
                    FROM jsonb_array_elements(registration_applications.evaluation_answers)
                        AS element(value),
                        LATERAL (
                            SELECT element.value->>'question_id' AS question_id,
                                   element.value->>'answer' AS answer
                        ) AS answer
                    WHERE {$where}
                )
                SQL,
            $bindings,
        );
    }
}
