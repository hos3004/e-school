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
    'group_not_found' => 'The selected group does not exist in this organization.',
    'program_not_attached' => 'Program ":program_id" is not attached to this group.',
    'course_not_assigned' => 'Course ":course_id" is not assigned to an active teacher in this group.',
    'individual_course_requires_empty_group' => 'An individual course group cannot accept more than one active student.',
    'teacher_profile_invalid' => 'An assigned teacher is not active in this organization.',
    'teacher_not_qualified' => 'An assigned teacher is not qualified for course ":course_id".',
    'program_not_found' => 'The selected program does not exist in this organization.',
    'course_not_found' => 'The selected course does not exist in this organization.',
    'unassign_reason_required' => 'A reason is required to end a teacher assignment.',
    'detach_reason_required' => 'A reason is required to detach a program.',
    'activation_data_incomplete' => 'The group cannot be activated before completing: :missing.',
    'capacity_below_members' => 'The capacity entered (:capacity) is lower than the number of students already placed (:members).',
    'invalid_membership_transition' => 'A membership cannot move from “:from” to “:to”.',
];
