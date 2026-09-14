<?php

declare(strict_types=1);

return [
    'schedule.created' => [
        'subject' => 'Course schedule confirmed',
        'body' => 'The schedule for {{target_name}} in {{course_name}} ({{course_code}}) with {{teacher_name}} has been confirmed. Session duration: {{duration_minutes}} minutes. Total sessions: {{session_count}}. Session times: {{schedule_times}}',
        'parameters' => ['target_name', 'course_name', 'course_code', 'teacher_name', 'duration_minutes', 'session_count', 'schedule_times'],
    ],
    'registration.submitted' => [
        'subject' => 'Registration request received',
        'body' => 'Your registration request was received. You will be notified when the review is complete.',
    ],
    'registration.approved' => [
        'subject' => 'Registration approved',
        'body' => 'Your registration request was approved. You can now continue with the onboarding steps.',
    ],
    'registration.rejected' => [
        'subject' => 'Registration request update',
        'body' => 'Your registration request could not be approved. Review the request details or contact the administration.',
    ],
    'teacher.availability.approved' => [
        'subject' => 'Availability approved',
        'body' => 'The administration approved the availability you submitted.',
    ],
    'student.assigned_to_teacher' => [
        'subject' => 'Teacher assigned',
        'body' => 'The student was assigned to a teacher. Upcoming sessions will appear on the schedule.',
    ],
    'student.assigned_to_group' => [
        'subject' => 'Group assignment completed',
        'body' => 'The student was added to the learning group successfully.',
    ],
    'session.scheduled' => [
        'subject' => 'Session scheduled',
        'body' => 'The session is scheduled to start at {{scheduled_start}}.',
        'parameters' => ['scheduled_start'],
    ],
    'session.rescheduled' => [
        'subject' => 'Session time changed',
        'body' => 'The replacement session is scheduled for {{makeup_start}}.',
        'parameters' => ['makeup_start'],
    ],
    'teacher.apology.submitted' => [
        'subject' => 'Teacher apology received',
        'body' => 'The teacher apology was recorded and approved, and the substitute search started.',
    ],
    'student.apology.submitted' => [
        'subject' => 'Student apology recorded',
        'body' => 'The student apology was recorded and the relevant parties were notified.',
    ],
    'postponement.requested' => [
        'subject' => 'Session postponement requested',
        'body' => 'A request to postpone the session to {{proposed_start}} was recorded and the relevant parties were notified.',
        'parameters' => ['proposed_start'],
    ],
    'postponement.alternative_proposed' => [
        'subject' => 'Teacher proposed an alternative time',
        'body' => 'The teacher proposed {{teacher_proposed_start}} as an alternative session time.',
        'parameters' => ['teacher_proposed_start'],
    ],
    'postponement.scheduled' => [
        'subject' => 'Session postponed',
        'body' => 'The alternative session time was confirmed for {{agreed_start}}.',
        'parameters' => ['agreed_start'],
    ],
    'postponement.rejected' => [
        'subject' => 'Postponement request rejected',
        'body' => 'The session postponement request was rejected. Review the recorded reason in the platform.',
    ],
    'teacher.apology.approved' => [
        'subject' => 'Apology approved',
        'body' => 'The teacher apology was approved automatically and the substitute search started.',
    ],
    'teacher.apology.rejected' => [
        'subject' => 'Apology not approved',
        'body' => 'The supervisor did not approve the apology request. Review the decision details in the platform.',
    ],
    'session.substitute.required' => [
        'subject' => 'A substitute teacher is required',
        'body' => 'The search for a substitute teacher has started and requires supervisor follow-up.',
    ],
    'session.substitute.assigned' => [
        'subject' => 'Substitute teacher assigned',
        'body' => 'A substitute teacher was assigned for the session scheduled at {{scheduled_start}}.',
        'parameters' => ['scheduled_start'],
    ],
    'session.substitute.candidates_updated' => [
        'subject' => 'Substitute candidates updated',
        'body' => 'The automatic search found {{candidate_count}} qualified and available substitute candidates for the session.',
        'parameters' => ['candidate_count'],
    ],
    'session.substitute.changed' => [
        'subject' => 'Substitute teacher changed',
        'body' => 'The substitute teacher was updated for the session scheduled at {{scheduled_start}}.',
        'parameters' => ['scheduled_start'],
    ],
    'session.approaching' => [
        'subject' => 'Your session is approaching',
        'body' => 'Reminder: your {{course_name}} session starts at {{scheduled_start}} and lasts {{duration_minutes}} minutes.',
        'parameters' => ['course_name', 'scheduled_start', 'duration_minutes'],
    ],
    'classroom.guest_invited' => [
        'subject' => 'Classroom guest invitation',
        'body' => 'A secure, limited guest invitation was created for the classroom.',
    ],
    'teacher.apology.second_warning' => [
        'subject' => 'Second apology warning',
        'body' => 'The apology record reached the second warning level in the current window.',
    ],
    'teacher.apology.third_escalation' => [
        'subject' => 'Apology record escalated',
        'body' => 'The apology record reached the third escalation level and requires administration review.',
    ],
    'session.report.due' => [
        'subject' => 'Session report deadline approaching',
        'body' => 'Please complete the session report before its configured deadline.',
    ],
    'session.report.late' => [
        'subject' => 'Session report is late',
        'body' => 'The session report was not completed within the deadline and is now marked as late.',
    ],
    'discipline.action_applied' => [
        'subject' => 'Discipline notice',
        'body' => 'A discipline action related to your enrollment was recorded. Review the details in your account or contact the administration.',
    ],
    'discipline.student_frozen' => [
        'subject' => 'Enrollment frozen',
        'body' => 'Your enrollment has been temporarily frozen, and course access is paused until it is reactivated. Contact the administration for details.',
    ],
    'assignment.created' => [
        'subject' => 'New assignment',
        'body' => 'A new assignment has been assigned to you. Review its details and due date in your account.',
    ],
    'assignment.submitted' => [
        'subject' => 'Assignment submitted',
        'body' => 'A student has submitted an assignment and it is now ready for grading.',
    ],
    'submission.graded' => [
        'subject' => 'Assignment graded',
        'body' => 'Your grade has been recorded: {{score}} out of {{max_score}}. View the feedback in your account.',
        'parameters' => ['score', 'max_score'],
    ],
    'schedule.change.requested' => [
        'subject' => 'Permanent lesson time change requested',
        'body' => 'Teacher {{teacher_name}} asked to move {{course_name}} sessions from {{current_schedule}} to {{proposed_schedule}}. The new time applies only once every student accepts; the response window closes on {{expires_at}}.',
        'parameters' => ['teacher_name', 'course_name', 'current_schedule', 'proposed_schedule', 'expires_at'],
    ],
    'schedule.change.applied' => [
        'subject' => 'New permanent lesson time approved',
        'body' => 'Every student accepted the new time for {{course_name}} sessions: {{proposed_schedule}}. It applies to sessions from {{effective_from}}.',
        'parameters' => ['course_name', 'proposed_schedule', 'effective_from'],
    ],
    'schedule.change.rejected' => [
        'subject' => 'Permanent lesson time change not approved',
        'body' => 'The request to move {{course_name}} sessions to {{proposed_schedule}} ended without approval; the current time stays in place.',
        'parameters' => ['course_name', 'proposed_schedule'],
    ],
    'session.join_link.teacher' => [
        'subject' => 'Your session starts soon — entry link',
        'body' => '{{course_name}} starts in {{minutes_until_start}} minutes, at {{scheduled_start}}. Open the session page and sign in from there so the system records your attendance and credits the session to your balance: {{join_url}}',
        'parameters' => ['course_name', 'minutes_until_start', 'scheduled_start', 'join_url'],
    ],
    'session.join_link.student' => [
        'subject' => 'Your session starts soon — entry link',
        'body' => '{{course_name}} starts in {{minutes_until_start}} minutes, at {{scheduled_start}}. Join the classroom directly from this link: {{join_url}}',
        'parameters' => ['course_name', 'minutes_until_start', 'scheduled_start', 'join_url'],
    ],
    'discipline.absence_recorded' => [
        'subject' => 'Session absence recorded',
        'body' => 'An absence was recorded for {{student_name}} from the {{course_name}} session on {{scheduled_start}}. The absence count is now {{absence_count}} for the current period. Please note that the system is set to freeze the enrolment automatically and release the seat to another student once absences without prior excuse reach {{freeze_threshold}}.',
        'parameters' => ['student_name', 'course_name', 'scheduled_start', 'absence_count', 'freeze_threshold'],
    ],
];
