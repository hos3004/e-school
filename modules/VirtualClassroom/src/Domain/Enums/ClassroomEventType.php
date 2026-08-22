<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\Enums;

/**
 * أنواع الأحداث الموحّدة القادمة من المزوّد أو من المنصة.
 *
 * المزوّد يرسل أسماء خاصة به (مثل meeting.started في BBB)؛
 * الـ Adapter يحوّلها إلى هذه الأنواع المستقرة قبل تخزينها،
 * حتى لا يعرف باقي الكود شيئًا عن تفاصيل المزوّد.
 */
enum ClassroomEventType: string
{
    /** فُتح الفصل عند المزوّد. */
    case MeetingStarted = 'meeting_started';

    /** أُغلق الفصل وطُرد جميع المشاركين. */
    case MeetingEnded = 'meeting_ended';

    /** انضم مشارك إلى الفصل. */
    case ParticipantJoined = 'participant_joined';

    /** غادر مشارك الفصل. */
    case ParticipantLeft = 'participant_left';

    /** بدأ التسجيل. */
    case RecordingStarted = 'recording_started';

    /** توقف التسجيل مؤقتًا أو نهائيًا. */
    case RecordingPaused = 'recording_paused';

    public function label(): string
    {
        return __('virtualclassroom::event_types.'.$this->value);
    }
}
