<?php

declare(strict_types=1);

/*
| أنواع الحصص كما تعرّفها config/academic.php → session_types.
| تُستهلك عبر __('sessions::session_types.key').
*/

return [
    'individual' => 'فردية',
    'group' => 'جماعية',
    'webinar' => 'ندوة',
    'makeup' => 'حصة تلافي',
    'assessment' => 'حصة تقييم',
];
