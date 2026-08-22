<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Enums;

/**
 * دور المشارك داخل المحادثة.
 *
 * owner     : منشئ المحادثة — يدير المشاركين والإشراف.
 * moderator : مشرف يعيَّن صراحة.
 * member    : مشارك عادي.
 */
enum ParticipantRole: string
{
    case Owner = 'owner';

    case Moderator = 'moderator';

    case Member = 'member';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Owner => $target === self::Owner,
            self::Moderator => in_array($target, [self::Moderator, self::Member], true),
            self::Member => in_array($target, [self::Member, self::Moderator], true),
        };
    }

    /** هل يملك هذا الدور صلاحية إشراف على المحادثة؟ */
    public function canModerate(): bool
    {
        return in_array($this, [self::Owner, self::Moderator], true);
    }

    public function label(): string
    {
        return __('messaging::enums.participant_role.'.$this->value);
    }
}
