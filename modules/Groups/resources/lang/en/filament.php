<?php

declare(strict_types=1);

/*
| Filament UI texts of the Groups module.
| Consumed via __('groups::filament.key').
*/

return [
    'navigation_group' => 'Academic Affairs',
    'navigation_label' => 'Groups',
    'model_label' => 'Group',
    'plural_model_label' => 'Groups',
    'name_locale_key' => 'Language',
    'name_value_label' => 'Name',
    'active_members_count' => 'Active students',
    'not_available' => 'Not available',
    'fields' => [
        'name_ar' => 'Arabic group name',
        'name_en' => 'English group name',
        'name_fr' => 'French group name',
        'reason_help' => 'State why the group is being created or changed for the audit trail.',
    ],
    'hub' => [
        'title' => 'Group operations hub',
        'overview' => 'Group overview',
        'available_places' => 'Available places',
        'programs' => 'Programs',
        'teachers' => 'Teachers',
        'students' => 'Students',
        'sessions' => 'Sessions',
        'empty' => 'No data is available in this section.',
        'fields' => [
            'teacher' => 'Teacher',
            'student' => 'Student',
            'student_code' => 'Student code',
            'session' => 'Session',
            'scheduled_start' => 'Session start',
            'scheduled_end' => 'Session end',
        ],
    ],
    'actions' => [
        'place_student' => 'Place student',
        'student_placed' => 'The student was placed in this group.',
        'assign_teacher' => 'Assign teacher',
        'teacher_assigned' => 'The teacher was assigned to this group.',
        'attach_program' => 'Attach program',
        'program_attached' => 'The program was attached to this group.',
        'activate' => 'Activate group',
        'complete' => 'Complete group',
        'archive' => 'Archive group',
        'active_success' => 'The group was activated.',
        'completed_success' => 'The group was completed.',
    ],
];
