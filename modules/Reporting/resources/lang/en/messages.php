<?php

declare(strict_types=1);

/*
| General messages of the Reporting module.
| Consumed via __('reporting::messages.key') — no user-facing text outside translation files.
*/

return [

    'organization_required' => 'An active organization is required to view reports.',
    'invalid_period' => 'The period must be valid and no longer than :days days.',
    'invalid_period_dates' => 'The period start and end dates are invalid.',
    'pdf_failed' => 'The PDF could not be generated. Please try again.',
    'report_failed' => 'The report could not be loaded. Please try again.',

    'seeder_no_enrollments' => 'No enrollments exist yet — student dashboards skipped.',

    'seeder_no_staff' => 'No staff profiles exist yet — teacher dashboards skipped.',

    'dashboard_corrected' => 'Dashboard corrected with documented reason.',

    'snapshot_recorded' => "Today's organization snapshot has been recorded.",

];
