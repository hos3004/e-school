<?php

declare(strict_types=1);

/*
| Archive page copy for the console.
|
| "Archived" here means closed and taken out of the working view, not deleted.
| The wording keeps that difference, because the admin decides from it: someone
| who thinks the button deletes will never press it.
*/

return [
    'title' => 'Archive',
    'description' => 'What has ended and left the daily view — with all of its data and its closing summary. Nothing here is deleted, and everything can be reopened.',

    'sections' => [
        'archived' => 'Archived',
        'candidates' => 'Ready to archive',
        'programs' => 'Programs',
        'courses' => 'Courses',
        'groups' => 'Groups',
    ],

    'empty' => [
        'archived' => 'Nothing has been archived yet.',
        'candidates' => 'There is nothing to archive right now.',
    ],

    'labels' => [
        'closed_at' => 'Archived on',
        'closed_by' => 'Archived by',
        'reason' => 'Reason',
        'summary' => 'Closing summary',
        'captured_at' => 'Snapshot taken',
    ],

    'summary' => [
        'levels_total' => 'Levels',
        'courses_total' => 'Courses',
        'courses_closed' => 'Archived courses',
        'enrollments_total' => 'Enrollments',
        'enrollments_completed' => 'Completed enrollments',
        'enrollments_withdrawn' => 'Withdrawn enrollments',
        'students_distinct' => 'Beneficiaries',
        'sessions_total' => 'Sessions',
        'sessions_completed' => 'Sessions held',
        'sessions_cancelled' => 'Sessions cancelled',
        'sessions_stale' => 'Past sessions never closed',
        'sessions_other' => 'Other sessions (no-show/excused/postponed/superseded)',
        'courses_open' => 'Courses not archived',
        'teachers_distinct' => 'Teachers',
        'first_session_at' => 'First session',
        'last_session_at' => 'Last session',
        'planned_sessions' => 'Planned sessions',
        'members_total' => 'Members',
        'teachers_total' => 'Assigned teachers',
        'programs_total' => 'Linked programs',
        'capacity' => 'Capacity',
        'status' => 'Status',
        'starts_on' => 'Starts',
        'ends_on' => 'Ends',
    ],

    'blockers' => [
        'title' => 'Cannot be archived yet',
        'help' => 'Archiving deletes nothing, but it hides from the working view — so it is refused while live work sits underneath.',
        'courses_active' => 'Active courses: :count',
        'enrollments_live' => 'Open enrollments: :count',
        'sessions_open' => 'Sessions not in a final state: :count',
        'members_active' => 'Students still enrolled: :count',
    ],

    'actions' => [
        'preview' => 'Preview summary',
        'close' => 'Archive',
        'reopen' => 'Reopen',
        'cancel' => 'Cancel',
        'loading' => 'Calculating…',
    ],

    'dialog' => [
        'close_title' => 'Archive :name',
        'reopen_title' => 'Reopen :name',
        'reason' => 'Reason',
        'reason_placeholder' => 'Why are you archiving this now?',
        'reason_help' => 'The reason is stored with the summary in the audit trail, and stays readable after reopening.',
        'closable' => 'This summary will be frozen exactly as shown when you archive.',
    ],

    'flash' => [
        'closed' => 'Archived. The summary is saved, and you can reopen it whenever you want.',
        'reopened' => 'Reopened and back in the working view.',
    ],
];
