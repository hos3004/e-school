<?php

declare(strict_types=1);

/*
| Error messages of the Reporting module.
| Consumed via __('reporting::errors.key') — error keys describe meaning, not text.
*/

return [

    'missing_projection_key' => 'Projection data incomplete: the field ":field" is required to update the dashboard.',

    'unknown_student_metric' => 'Unknown student dashboard metric: ":metric". See the map in config/reporting.php.',

    'unknown_teacher_metric' => 'Unknown teacher dashboard metric: ":metric". See the map in config/reporting.php.',

    'negative_payout_delta' => 'Negative payout amount (:amount_minor) cannot be credited to the teacher dashboard.',

    'negative_counter_value' => 'Counter value must be zero or greater (received: :value).',

    'correction_reason_required' => 'A manual correction requires a written reason.',

    'correction_reason_length' => 'The correction reason must be between :min and :max characters for adequate documentation.',

    'unknown_correction_column' => 'The column ":column" is not correctable.',

    'dashboard_not_found' => 'No dashboard exists for enrollment ":enrollment_id" — it may not have been projected yet.',

    'snapshot_exists' => "Today's snapshot already exists and will be updated instead of duplicated.",

];
