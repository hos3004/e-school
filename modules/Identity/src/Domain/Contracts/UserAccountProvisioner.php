<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Contracts;

use Modules\Identity\Domain\Contracts\DTOs\CreateUserAccountData;
use Modules\Identity\Domain\Contracts\DTOs\UserAccountData;

/**
 * منفذ إنشاء الحساب وربط حساب معروف صراحةً دون تسريب نموذج Identity.
 *
 * الربط لا يبحث بالبريد/الهاتف ثم يختار حسابًا تلقائيًا؛ يجب أن يمرر المستهلك
 * userId موثوقًا (مثل المستخدم المصادق عليه) وأن يثبت تطابق جهة اتصال واحدة.
 */
interface UserAccountProvisioner
{
    public function create(CreateUserAccountData $data): UserAccountData;

    public function confirmExistingAccount(
        string $organizationId,
        string $userId,
        ?string $email,
        ?string $phone,
    ): UserAccountData;
}
