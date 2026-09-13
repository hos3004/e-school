<?php

declare(strict_types=1);

/*
| Error messages of the Scheduling module.
| Consumed via __('scheduling::errors.key') — keys describe meaning, not wording.
*/

return [
    'postponement_invalid_transition' => 'The postponement request cannot transition from :from to :to.',
    'rejection_reason_required' => 'A rejection reason is required.',
    'reason_required' => 'An operation reason is required.',
    'session_not_found' => 'The requested session was not found.',
    'session_not_postponable' => 'The session cannot be postponed from its current status (:status).',
    'postponement_not_found' => 'The postponement request was not found.',
    'postponement_notice_not_met' => 'Postponement must be requested at least :required minutes before the session.',
    'postponement_already_pending' => 'A postponement request is already pending for this session.',
    'proposed_start_in_past' => 'The proposed time must be in the future.',
    'student_not_participant' => 'The student is not a participant in this session.',
    'teacher_not_assigned_to_session' => 'You cannot request postponement for a session not assigned to you.',
    'outside_makeup_window' => 'The make-up session must occur within :days days of the original.',
    'conflict_detected' => 'The time conflicts with :count existing sessions.',
    'weekdays_invalid' => 'Select at least one valid weekday.',
    'interval_invalid' => 'The weekly recurrence interval is invalid.',
    'rrule_invalid' => 'The recurrence rule is invalid or unsupported.',
    'timezone_invalid' => 'The timezone is invalid.',
    'target_invalid' => 'Select exactly one group or student.',
    'course_not_found' => 'The course was not found in this organization.',
    'teacher_unchanged' => 'The selected teacher already teaches this schedule.',
    'course_already_scheduled' => 'The student already has an active schedule for this course; change or remove its teacher first.',
    'teacher_not_eligible' => 'The teacher is inactive or not qualified for this course.',
    'teacher_not_assigned' => 'The teacher is not assigned to this group course for the schedule period.',
    'ends_before_start' => 'The schedule end date cannot precede its start date.',
    'duration_invalid' => 'The session duration is not approved.',
    'course_mode_mismatch' => 'The schedule target does not match the course session mode.',
    'group_not_eligible' => 'The group is inactive or not linked to the course program.',
    'student_not_schedulable' => 'The student has no enrollment that permits scheduling in this program.',
    'schedule_inactive' => 'The schedule template is inactive.',
    'teacher_on_leave' => 'The teacher is on approved leave on :date.',
    'outside_teacher_availability' => 'The time is outside the teacher’s declared availability.',
    'individual_quran_course_missing' => 'The Individual Quran course is missing or inactive.',
    'individual_student_not_eligible' => 'The student is not currently eligible for Individual Quran placement or already has an active schedule.',
    'individual_slot_unavailable' => 'The selected session time is no longer available. Choose another time from the list.',
    'weekly_slots_invalid' => 'Choose one valid time for each distinct session weekday.',
    'bulk_no_eligible_students' => 'None of the selected students is eligible for Individual Quran placement.',
    'bulk_insufficient_slots' => 'Available independent slots (:slots) are fewer than eligible students (:students).',
    'bulk_limit_exceeded' => 'No more than :maximum students can be placed in one operation.',
    'schedule_not_found' => 'The schedule template was not found.',
    'schedule_not_assigned_to_teacher' => 'You cannot request a time change for a schedule that is not assigned to you.',
    'group_slots_single_time' => 'Group sessions start at the same time on every day.',
    'schedule_change_not_different' => 'The proposed weekly time matches the current one.',
    'schedule_change_already_pending' => 'A schedule change request is already pending for this schedule.',
    'schedule_change_no_students' => 'This schedule has no active students to approve the change.',
    'schedule_change_not_pending' => 'The schedule change request is no longer open for responses.',
    'schedule_change_expired' => 'The response window for the schedule change request has elapsed.',
    'schedule_change_response_not_allowed' => 'You have no pending response on this schedule change request.',
    'schedule_change_invalid_transition' => 'A schedule change request cannot move from :from to :to.',
];
