<?php

declare(strict_types=1);

namespace Modules\Audit\Domain\Enums;

/**
 * الأفعال القياسية لقيود التدقيق داخل هذا الموديول.
 *
 * عمود action في audit_log نصّي (128) لأن بقية الموديولات قد تسجّل
 * أفعالًا خاصة بها (presence.updated وغيرها)؛ هذه الحالات هي المفردات
 * المعتمدة التي يكتبها موديول Audit نفسه، مع مُساعد fromString آمن.
 */
enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case ForceDeleted = 'force_deleted';
    case LoggedIn = 'logged_in';
    case LoggedOut = 'logged_out';
    case PermissionChanged = 'permission_changed';

    public function label(): string
    {
        return __('audit::labels.actions.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Created => 'success',
            self::Updated => 'info',
            self::Deleted, self::ForceDeleted => 'danger',
            self::Restored => 'warning',
            self::LoggedIn, self::LoggedOut => 'gray',
            self::PermissionChanged => 'purple',
        };
    }

    /**
     * تحويل نصّ حر من قيود الموديولات الأخرى إلى حالة قياسية أو null.
     */
    public static function fromString(string $action): ?self
    {
        return collect(self::cases())
            ->first(fn (self $case): bool => $case->value === strtolower($action));
    }
}
