<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Identity\Domain\Models\User;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * كلمة مرور مؤقتة يصدرها إداري ليبلغ بها صاحب الحساب.
 *
 * لماذا تُولَّد ولا تُقرأ: كلمة المرور مخزّنة hash فلا سبيل لاسترجاع القائمة.
 * وهذا يعني أن إرسال «بيانات الحساب» بكلمة مرور يبطل كلمة المرور الحالية
 * فورًا — وهو أثر جانبي حقيقي على المستخدم، لذلك يُشترط سبب مكتوب ويُسجَّل
 * في سجل التدقيق، ويُلزَم صاحب الحساب بتغييرها عند أول دخول.
 *
 * كلمة المرور المولّدة لا تُخزَّن ولا تُسجَّل في التدقيق؛ تُعاد للمستدعي مرة
 * واحدة ليضعها في الرسالة، ثم تضيع.
 */
final readonly class IssueTemporaryPassword
{
    public function __construct(
        private Transaction $transaction,
        private AuditRecorder $audit,
    ) {}

    public function execute(User $user, string $actorId, string $reason): string
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw BusinessRuleViolation::make(
                'identity.temporary_password_reason_required',
                'identity::errors.temporary_password_reason_required',
            );
        }

        $password = Str::password(
            length: max(8, (int) config('admission.account.temporary_password_length', 12)),
            symbols: false,
        );

        $this->transaction->run(function () use ($user, $password, $actorId, $reason): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'must_change_password' => true,
            ])->save();

            $this->audit->record(
                organizationId: (string) $user->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'identity.temporary_password_issued',
                auditableType: 'users',
                auditableId: (string) $user->getKey(),
                oldValues: null,
                // القيمة نفسها لا تُسجَّل — الأثر المسجَّل هو الإبطال والإلزام.
                newValues: ['must_change_password' => true],
                reason: $reason,
            );
        });

        return $password;
    }
}
