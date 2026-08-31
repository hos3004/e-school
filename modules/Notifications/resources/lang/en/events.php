<?php

declare(strict_types=1);

/*
| Notification event names as shown on the templates screen.
| The key (session.scheduled) is what the template stores and never changes;
| this is a display label only.
*/

return [

    'assignment.created' => 'New assignment',
    'assignment.submitted' => 'Assignment submitted',
    'submission.graded' => 'Submission graded',

    'classroom.guest_invited' => 'Classroom guest invited',

    'discipline.action_applied' => 'Discipline action applied',
    'discipline.student_frozen' => 'Student enrollment frozen',

    'registration.submitted' => 'Registration submitted',
    'registration.approved' => 'Registration approved',
    'registration.rejected' => 'Registration rejected',

    'session.scheduled' => 'Session scheduled',
    'session.rescheduled' => 'Session rescheduled',
    'session.approaching' => 'Session approaching',
    'session.joinable' => 'Session open to join',
    'session.report.due' => 'Session report due',
    'session.report.late' => 'Session report late',
    'session.substitute.required' => 'Substitute teacher required',
    'session.substitute.assigned' => 'Substitute teacher assigned',
    'session.substitute.changed' => 'Substitute teacher changed',

    'student.assigned_to_group' => 'Student assigned to group',
    'student.assigned_to_teacher' => 'Student assigned to teacher',

    'teacher.apology.submitted' => 'Teacher apology submitted',
    'teacher.apology.approved' => 'Teacher apology approved',
    'teacher.apology.rejected' => 'Teacher apology rejected',
    'teacher.apology.second_warning' => 'Teacher second warning',
    'teacher.apology.third_escalation' => 'Teacher third escalation',
    'teacher.availability.approved' => 'Teacher availability approved',
];
