<?php

declare(strict_types=1);

namespace Modules\SupportBot\Domain\Enums;

/**
 * صاحب الرسالة داخل محادثة البوت.
 */
enum MessageRole: string
{
    case User = 'user';
    case Bot = 'bot';
}
