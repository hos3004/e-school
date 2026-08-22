<?php

declare(strict_types=1);

/*
| Error messages of the Groups module.
| Consumed via __('groups::errors.key') — keys describe meaning, not wording.
*/

return [
    'code_taken' => 'The group code ":code" is already in use. Choose another code.',
    'ends_before_starts' => 'The end date (:ends_on) must be after the start date (:starts_on).',
    'already_archived' => 'This group is already archived and cannot be modified.',
    'invalid_status_transition' => 'Cannot move the group from ":from" to ":to".',
    'group_not_accepting_members' => 'The group is ":status" and does not accept changes to students, teachers, or programs.',
    'capacity_reached' => 'This group is full; the maximum capacity is :capacity students.',
    'already_enrolled' => 'Student ":student_profile_id" is already enrolled in this group.',
    'withdraw_reason_required' => 'A reason is required to withdraw a student from a group.',
    'archive_reason_required' => 'A reason is required to archive a group.',
    'membership_not_active' => 'Membership ":membership_id" is not active, so it cannot be withdrawn.',
    'teacher_already_assigned' => 'Teacher ":staff_profile_id" is already assigned to this course within the group.',
    'assignment_already_closed' => 'Teacher assignment ":assignment_id" is already closed and cannot be unassigned again.',
    'program_already_attached' => 'Program ":program_id" is already attached to this group.',
];
