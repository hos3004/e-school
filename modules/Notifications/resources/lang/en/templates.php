<?php

declare(strict_types=1);

return [
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
        'body' => 'The apology request was received and will be reviewed by a supervisor.',
    ],
    'teacher.apology.approved' => [
        'subject' => 'Apology approved',
        'body' => 'The supervisor approved the teacher apology and substitute follow-up has started.',
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
    'session.substitute.changed' => [
        'subject' => 'Substitute teacher changed',
        'body' => 'The substitute teacher was updated for the session scheduled at {{scheduled_start}}.',
        'parameters' => ['scheduled_start'],
    ],
    'session.approaching' => [
        'subject' => 'Your session is approaching',
        'body' => 'Reminder: the session starts at {{scheduled_start}}.',
        'parameters' => ['scheduled_start'],
    ],
    'session.joinable' => [
        'subject' => 'You can join the session now',
        'body' => 'The session join window is open. Use the secure join link from your schedule.',
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
];
