<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Events\UserStatusChanged;
use Modules\Identity\Domain\Models\User;
use Shared\Support\BusinessRuleViolation;

/**
 * تغيير حالة الحساب: تفعيل، إيقاف، تجميد — مع سبب مكتوب إلزامي.
 *
 * الانتقال يمر عبر canTransitionTo دائمًا. ممنوع على المستخدم أن
 * يغيّر حالته بنفسه (تدقيقًا وتحصينًا للصلاحيات).
 */
final readonly class ChangeUserStatus
{
    public function execute(User $target, UserStatus $to, string $reason, ?string $actorId = null): User
    {
        if ($reason === '') {
            throw BusinessRuleViolation::make(
                'identity.status_reason_required',
                'identity::errors.status_reason_required',
            );
        }

        $currentActorId = $actorId ?? (auth()->id() === null ? null : (string) auth()->id());
        if ($currentActorId !== null && $currentActorId === $target->id) {
            throw BusinessRuleViolation::make(
                'identity.self_status_change',
                'identity::errors.self_status_change',
            );
        }

        $from = $target->status;
        if (! $from->canTransitionTo($to)) {
            throw BusinessRuleViolation::make(
                'identity.invalid_status_transition',
                'identity::errors.invalid_status_transition',
                ['from' => $from->label(), 'to' => $to->label()],
            );
        }

        /** @var User $target */
        $target = DB::transaction(function () use ($target, $to): User {
            $target->status = $to;
            $target->save();

            return $target;
        });

        Event::dispatch(new UserStatusChanged(
            userId: $target->id,
            organizationId: $target->organization_id,
            from: $from->value,
            to: $to->value,
            reason: $reason,
        ));

        return $target;
    }
}
