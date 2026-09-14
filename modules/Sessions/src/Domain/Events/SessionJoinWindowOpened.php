<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Events;

/**
 * فُتحت نافذة الدخول إلى الحصة — يُرسل لكل طرف الرابط الذي يخصّه.
 *
 * الحدث يُنشر مرة لكل جمهور (ومرة لكل طالب في جمهور الطلاب) لأن الرابط نفسه
 * يختلف باختلاف المستلم: المعلم يأخذ رابط صفحة الحصة داخل النظام كي يسجّل
 * دخوله فيُحتسب حضوره وتُقيَّد مستحقاته، والطالب يأخذ رابط دخول مباشرًا
 * موقّعًا باسمه وحده. لذلك لا يُجمع المستلمون في حدث واحد: قيد الصندوق
 * الصادر يُركَّب من حمولة الحدث، فحمولة واحدة تعني رابطًا واحدًا للجميع.
 */
final class SessionJoinWindowOpened extends SessionEvent
{
    /**
     * @param list<string> $studentUserIds
     * @param array<string, string> $courseName
     */
    public function __construct(
        string $sessionId,
        string $organizationId,
        string $courseId,
        string $staffProfileId,
        public readonly string $audience,
        public readonly string $joinUrl,
        public readonly string $scheduledStart,
        public readonly string $scheduledEnd,
        public readonly array $studentUserIds,
        public readonly ?string $teacherUserId,
        public readonly array $courseName,
        public readonly int $minutesUntilStart,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($sessionId, $organizationId, $courseId, $staffProfileId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'sessions.join_window_opened';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'session_id' => $this->sessionId,
            'organization_id' => $this->organizationId,
            'course_id' => $this->courseId,
            'staff_profile_id' => $this->staffProfileId,
            'audience' => $this->audience,
            'join_url' => $this->joinUrl,
            'scheduled_start' => $this->scheduledStart,
            'scheduled_end' => $this->scheduledEnd,
            'student_user_ids' => $this->studentUserIds,
            'teacher_user_id' => $this->teacherUserId,
            'course_name' => $this->courseName,
            'minutes_until_start' => $this->minutesUntilStart,
        ];
    }
}
