<?php

declare(strict_types=1);

namespace Shared\Filament;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Contracts\UserAccountOperations;
use Shared\Support\BusinessRuleViolation;

/**
 * إجراء موحّد لرفع/استبدال/إزالة صورة الحساب (Avatar) من لوحة Filament.
 *
 * يُعاد استخدامه كما هو في صفحات: المستخدم، الطالب، الموظف/المعلم.
 * الرفع يذهب إلى المجلد المؤقت المُهيَّأ، والاعتماد والتنظيف والتدقيق
 * تديره UserAccountOperations::setAvatar داخل Identity — لا كتابة
 * مباشرة على users من أي موديول آخر.
 */
final class UserAvatarAction
{
    /**
     * @param string $organizationId مؤسسة صاحب الحساب (للتحقق والعزل)
     * @param string $userId معرّف حساب المستخدم المستهدف
     */
    public static function make(string $organizationId, string $userId): Action
    {
        return Action::make('upload_avatar')
            ->label(__('identity::avatars.action'))
            ->icon('heroicon-o-camera')
            ->color('gray')
            ->schema([
                FileUpload::make('avatar')
                    ->label(__('identity::avatars.field'))
                    ->image()
                    ->avatar()
                    ->imageEditor()
                    ->disk((string) config('avatars.disk'))
                    ->directory((string) config('avatars.tmp_directory'))
                    ->getUploadedFileNameForStorageUsing(static fn (): string => ((string) Str::ulid()).'.bin')
                    ->acceptedFileTypes((array) config('avatars.accepted_mime_types'))
                    ->maxSize((int) config('avatars.max_size_kb'))
                    ->maxFiles(1)
                    ->openable()
                    ->helperText(__('identity::avatars.upload_help', [
                        'max' => (int) config('avatars.max_size_kb'),
                    ])),
                Toggle::make('remove')
                    ->label(__('identity::avatars.remove'))
                    ->dehydrated(false),
                Textarea::make('reason')
                    ->label(__('identity::avatars.reason'))
                    ->helperText(__('identity::avatars.reason_help'))
                    ->maxLength(2000)
                    ->required()
                    ->columnSpanFull(),
            ])
            ->action(function (array $data) use ($organizationId, $userId): void {
                try {
                    app(UserAccountOperations::class)->setAvatar(
                        organizationId: $organizationId,
                        userId: $userId,
                        storedPath: ($data['remove'] ?? false) ? null : ($data['avatar'] ?? null),
                        actorId: (string) auth()->id(),
                        reason: (string) ($data['reason'] ?? ''),
                    );
                } catch (BusinessRuleViolation $violation) {
                    Notification::make()
                        ->title($violation->getMessage())
                        ->danger()
                        ->send();

                    throw new Halt;
                }

                Notification::make()
                    ->title(__('identity::avatars.saved'))
                    ->success()
                    ->send();
            });
    }
}
