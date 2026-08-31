<?php

declare(strict_types=1);

namespace Modules\Content\Domain\Enums;

enum MaterialStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Unpublished = 'unpublished';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft, self::Unpublished => [self::Published],
            self::Published => [self::Unpublished],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function grantsAccess(): bool
    {
        return $this === self::Published;
    }

    public function label(): string
    {
        return __('content::status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'success',
            self::Unpublished => 'warning',
        };
    }
}
