<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\ValueObjects;

/**
 * يشتق معرّف الغرفة الدائمة (classrooms.room_identity) من معرّف الجدول
 * المتكرر — نقطة واحدة تبني هذا الشكل حتى لا يختلف بين من ينشئ الغرفة
 * (ProvisionClassroomAction)، من يدوّرها (RotateClassroomLinkAction)،
 * ومن يحلّ الرابط الدائم (ClassroomPersistentStudentLinkController).
 */
final class RoomIdentity
{
    private const SCHEDULE_PREFIX = 'SCH-';

    public static function forSchedule(string $scheduleId): string
    {
        return self::SCHEDULE_PREFIX.$scheduleId;
    }

    /** يعكس forSchedule()، أو null إن لم تكن الهوية من هذا الشكل. */
    public static function scheduleIdFrom(string $roomIdentity): ?string
    {
        if (!str_starts_with($roomIdentity, self::SCHEDULE_PREFIX)) {
            return null;
        }

        return substr($roomIdentity, strlen(self::SCHEDULE_PREFIX));
    }
}
