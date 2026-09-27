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
 * الوضع المرن: يُطبَّق فقط حين تحمل الجدولة نفسها اختيار المرونة
 * (schedules.flexible_start = true) — قرار إداري خاص بكل جدول على حدة، ليس
 * صفة تلقائية لكل الحصص الفردية. عندئذٍ تصبح النافذة اليوم المحلي كاملًا
 * (بتوقيت مؤسسة الحصة) بدل ±دقائق، لأن المعلم والطالب تراضيا على ذلك تحديدًا
 * لهذا الجدول. الموعد المجدول لا يتغيّر: هذا حساب نافذة الدخول فقط، ولا يمس
 * التذكيرات أو التقارير أو المستحقات.
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
        bool $scheduleAllowsFlexibleStart,
        bool $isTeacher,
    ): array {
        // مفتاح إيقاف عام يبقى بيد الإعداد، فوق اختيار الجدول نفسه — يسمح
        // بتعطيل الميزة كاملة في حادث تشغيلي دون لمس كل الجداول المفعّلة.
        if ($scheduleAllowsFlexibleStart && (bool) config('scheduling.flexible_individual_start.enabled')) {
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
