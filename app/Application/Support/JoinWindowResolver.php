<?php

declare(strict_types=1);

namespace App\Application\Support;

use Carbon\CarbonImmutable;
use Modules\Organization\Domain\Contracts\SchoolClockQueries;
use Throwable;

/**
 * حساب نافذة الدخول لحصة واحدة.
 *
 * المصدر الوحيد لهذا الحساب — تستدعيه بوابة الدخول (EnterClassroom) وصفحتا
 * الطالب والمعلم، حتى لا يفترق ما يُعرض في الواجهة عن ما يفرضه الخادم فعليًا.
 *
 * الوضع الافتراضي: ±دقائق حول الموعد المجدول (config('virtual-classroom.join_window')).
 * الوضع المرن: يُطبَّق فقط على الحصص الفردية عند تفعيل
 * config('scheduling.flexible_individual_start') — تصبح النافذة اليوم المحلي
 * كاملًا (بتوقيت مؤسسة الحصة) بدل ±دقائق، لأن المعلم والطالب قد يتراضيان على
 * أي وقت خلال يوم الموعد نفسه. الموعد المجدول لا يتغيّر: هذا حساب نافذة
 * الدخول فقط، ولا يمس التذكيرات أو التقارير أو المستحقات.
 */
final class JoinWindowResolver
{
    /**
     * [canJoinAt, canJoinUntil, isFlexible]
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: bool}
     */
    public static function resolve(
        CarbonImmutable $scheduledStart,
        CarbonImmutable $scheduledEnd,
        string $organizationId,
        ?string $sessionType,
        bool $isTeacher,
    ): array {
        if (self::isFlexibleEligible($sessionType)) {
            $timezone = self::organizationTimezone($organizationId);
            $localDay = $scheduledStart->setTimezone($timezone)->startOfDay();

            return [
                $localDay->setTimezone('UTC'),
                $localDay->addDay()->setTimezone('UTC'),
                true,
            ];
        }

        $beforeMinutes = $isTeacher
            ? (int) config('virtual-classroom.join_window.teacher_before_minutes')
            : (int) config('virtual-classroom.join_window.before_minutes');
        $afterMinutes = (int) config('virtual-classroom.join_window.after_minutes');

        return [
            $scheduledStart->subMinutes(max(0, $beforeMinutes)),
            $scheduledEnd->addMinutes(max(0, $afterMinutes)),
            false,
        ];
    }

    public static function isFlexibleEligible(?string $sessionType): bool
    {
        if ($sessionType === null || !(bool) config('scheduling.flexible_individual_start.enabled')) {
            return false;
        }

        $eligibleTypes = (array) config('scheduling.flexible_individual_start.session_types', []);

        return in_array($sessionType, $eligibleTypes, true);
    }

    /**
     * تُقرأ عبر عقد Organization العام لا بجدول مباشر، حتى لا يعرف هذا الملف
     * شكل جدول organizations. فشل الاستعلام (مؤسسة غير موجودة، أو منطقة زمنية
     * غير صالحة في الإعداد) يؤول إلى UTC بدل كسر الدخول للفصل بالكامل.
     */
    private static function organizationTimezone(string $organizationId): string
    {
        try {
            $timezone = app(SchoolClockQueries::class)->forOrganization($organizationId)['timezone'];
        } catch (Throwable) {
            return 'UTC';
        }

        if ($timezone === '') {
            return 'UTC';
        }

        try {
            new \DateTimeZone($timezone);
        } catch (\Exception) {
            return 'UTC';
        }

        return $timezone;
    }
}
