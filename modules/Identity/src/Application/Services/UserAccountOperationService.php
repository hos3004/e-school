<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Identity\Application\Actions\ChangeUserStatus;
use Modules\Identity\Application\Actions\UpdateUserProfile;
use Modules\Identity\Domain\Contracts\UserAccountOperations;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\User;
use Shared\Support\BusinessRuleViolation;

/**
 * بوابة الكتابة الإدارية على حساب مستخدم — تنتمي لموديول Identity.
 *
 * ترفض أي عملية لحساب خارج المؤسسة المطلوبة قبل لمس النموذج،
 * وتفوّض الحقول الآمنة لـ UpdateUserProfile والحالة لـ ChangeUserStatus.
 */
final readonly class UserAccountOperationService implements UserAccountOperations
{
    private const EDITABLE_FIELDS = ['name', 'phone', 'phone_country', 'locale', 'timezone'];

    public function __construct(
        private UpdateUserProfile $updateProfile,
        private ChangeUserStatus $changeUserStatus,
        private AuditRecorder $audit,
    ) {}

    public function updateProfile(
        string $organizationId,
        string $userId,
        array $fields,
        string $actorId,
        string $reason,
    ): void {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'identity.update_reason_required',
                'identity::errors.status_reason_required',
            );
        }

        /** @var User $user */
        $user = $this->assertAccountInOrganization($organizationId, $userId);

        $fields = collect($fields)
            ->only(self::EDITABLE_FIELDS)
            ->filter(static fn (mixed $value): bool => $value !== null && (string) $value !== '')
            ->all();

        if ($fields === []) {
            return;
        }

        $old = [];
        foreach ($fields as $field => $newValue) {
            $old[$field] = $user->getAttribute($field);
        }

        $this->updateProfile->execute($user, $fields);

        $this->audit->record(
            organizationId: $organizationId,
            actorId: $actorId,
            actorType: 'user',
            action: 'identity.account_updated',
            auditableType: 'user',
            auditableId: (string) $user->getKey(),
            oldValues: $old,
            newValues: $fields,
            reason: trim($reason),
        );
    }

    public function changeStatus(
        string $organizationId,
        string $userId,
        string $status,
        string $actorId,
        string $reason,
    ): void {
        /** @var User $user */
        $user = $this->assertAccountInOrganization($organizationId, $userId);

        $target = UserStatus::tryFrom($status);

        if ($target === null) {
            throw BusinessRuleViolation::make(
                'identity.invalid_status_value',
                'identity::errors.invalid_status_transition',
                ['to' => $status],
            );
        }

        $this->changeUserStatus->execute($user, $target, $reason, $actorId);
    }

    private function assertAccountInOrganization(string $organizationId, string $userId): User
    {
        /** @var User|null $user */
        $user = DB::transaction(fn (): ?User => User::query()
            ->whereKey($userId)
            ->where('organization_id', $organizationId)
            ->first());

        if ($user === null) {
            throw BusinessRuleViolation::make(
                'identity.organization_mismatch',
                'identity::errors.organization_mismatch',
            );
        }

        return $user;
    }

    public function setAvatar(
        string $organizationId,
        string $userId,
        ?string $storedPath,
        string $actorId,
        string $reason,
    ): void {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'identity.update_reason_required',
                'identity::errors.status_reason_required',
            );
        }

        /** @var User $user */
        $user = $this->assertAccountInOrganization($organizationId, $userId);

        /** @var Filesystem $disk */
        $disk = Storage::disk((string) config('avatars.disk'));

        $oldPath = $user->avatar_path;
        $finalPath = null;

        if (is_string($storedPath) && $storedPath !== '') {
            $finalPath = self::adoptUploadedAvatar($disk, $storedPath, $organizationId, $userId);
        }

        // الحفظ أولًا، وحذف القديم بعد النجاح فقط — لا يتيمة عند الفشل.
        DB::transaction(function () use ($user, $finalPath): void {
            $user->forceFill(['avatar_path' => $finalPath])->save();
        });

        if ($oldPath !== null && $oldPath !== $finalPath && $disk->exists($oldPath)) {
            $disk->delete($oldPath);
        }

        // تنظيف أي بقايا في المجلد المؤقت عند فشل التبنّي المسبق.
        if (is_string($storedPath) && $storedPath !== '' && $disk->exists($storedPath)) {
            $disk->delete($storedPath);
        }

        $this->audit->record(
            organizationId: $organizationId,
            actorId: $actorId,
            actorType: 'user',
            action: 'identity.avatar_updated',
            auditableType: 'user',
            auditableId: (string) $user->getKey(),
            oldValues: ['avatar_path' => $oldPath],
            newValues: ['avatar_path' => $finalPath],
            reason: trim($reason),
        );
    }

    /**
     * نقل الملف المرفوع من المجلد المؤقت إلى مساره النهائي باسم عشوائي آمن
     * (ULID) بعد التحقق أنّه صورة حقيقية على الخادم — لا ثقة بالمتصفح.
     */
    private static function adoptUploadedAvatar(Filesystem $disk, string $storedPath, string $organizationId, string $userId): string
    {
        if (!$disk->exists($storedPath)) {
            throw BusinessRuleViolation::make(
                'identity.avatar_missing',
                'identity::errors.avatar_not_uploaded',
            );
        }

        $mime = self::realImageMime($disk, $storedPath);

        if ($mime === null || !in_array($mime, (array) config('avatars.accepted_mime_types'), true)) {
            throw BusinessRuleViolation::make(
                'identity.avatar_invalid_image',
                'identity::errors.avatar_invalid_image',
            );
        }

        $directory = trim((string) config('avatars.directory'), '/');
        $extension = (string) config('avatars.extension_by_mime.'.$mime, 'png');
        // المسار يُنظَّم بحسب المؤسسة والمستخدم، والاسم عشوائي (ULID) لا يكشف الأصل.
        $finalPath = sprintf('%s/%s/%s/%s.%s', $directory, $organizationId, $userId, (string) Str::ulid(), $extension);

        $disk->move($storedPath, $finalPath);

        return $finalPath;
    }

    /** فحص المحتوى الفعلي للملف — الامتداد وحده لا يُعتمد أبدًا. */
    private static function realImageMime(Filesystem $disk, string $path): ?string
    {
        try {
            $stream = $disk->readStream($path);
        } catch (\Throwable) {
            return null;
        }

        if (!is_resource($stream)) {
            return null;
        }

        try {
            $head = (string) stream_get_contents($stream, 8192);

            return $head === '' ? null : (self::sniffImageMime($head));
        } finally {
            fclose($stream);
        }
    }

    /**
     * استنتاج MIME من البايتات الأولى (magic numbers) لأن ext/fileinfo
     * قد لا يتعامل مع streams على كل القرصات.
     *
     * @return non-empty-string|null
     */
    private static function sniffImageMime(string $bytes): ?string
    {
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }

        if (str_starts_with($bytes, "\x89PNG\r\n\x1A\n")) {
            return 'image/png';
        }

        if (strlen($bytes) >= 12 && str_starts_with(substr($bytes, 0, 4), 'RIFF')
            && str_starts_with(substr($bytes, 8, 4), 'WEBP')) {
            return 'image/webp';
        }

        return null;
    }
}
