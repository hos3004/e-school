<?php

declare(strict_types=1);

namespace Modules\Students\Application\Services;

use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;

/**
 * تفاصيل طلب التسجيل التي تحتاجها رسائل الإشعار.
 *
 * أحداث التسجيل الثلاثة (تقديم/قبول/رفض) تعرض الكورس المطلوب، والكورس يعيش
 * في موديول Academics — فيُقرأ عبر عقده العام لا عبر جداوله. اسم الكورس
 * خريطة لغات لأن المحرّك يختار لغة كل مستلم عند التركيب لا عند النشر.
 *
 * القالب يرفض أي بارامتر غير موجود ويُسقط الإشعار كله؛ لذلك لا تُعاد هنا
 * قيمة فارغة أبدًا: طلب بلا كورس يأخذ نصًا بديلًا مترجمًا يبقي الجملة مفهومة.
 */
final readonly class RegistrationNotificationDetails
{
    public function __construct(private AcademicCatalogQueries $catalog) {}

    /**
     * @return array<string, string>
     */
    public function courseName(string $organizationId, ?string $courseId): array
    {
        if ($courseId !== null && trim($courseId) !== '') {
            $course = $this->catalog->coursesByIds($organizationId, [trim($courseId)])[trim($courseId)] ?? null;

            if ($course !== null && $course->name !== []) {
                return $course->name;
            }
        }

        return $this->unspecifiedCourseName();
    }

    /**
     * @return array<string, string>
     */
    private function unspecifiedCourseName(): array
    {
        $names = [];

        foreach ($this->supportedLocales() as $locale) {
            $names[$locale] = (string) trans('students::registration.notifications.course_unspecified', [], $locale);
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function supportedLocales(): array
    {
        $locales = array_values(array_filter(
            (array) config('notifications.localization.supported', []),
            static fn (mixed $locale): bool => is_string($locale) && trim($locale) !== '',
        ));

        /** @var list<string> $locales */
        return $locales === []
            ? [(string) config('notifications.localization.fallback_locale', 'ar')]
            : $locales;
    }
}
