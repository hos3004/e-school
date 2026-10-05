<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Enums;

/** نوع مستلم التقرير الشهري المجمّع للبرنامج. */
enum DigestRecipientType: string
{
    case StaffEmail = 'staff_email';
    case CustomEmail = 'custom_email';
}
