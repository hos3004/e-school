<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Contracts;

/**
 * تحديد جمهور مستخدم عبر العقود القانونية (أدوار AccessControl) —
 * لا فحص role مباشر في أي واجهة.
 */
interface PopupAudienceResolver
{
    /**
     * @param string $modelType morph type الحساب من عقد Identity المعلن
     * @return list<string> قيم PopupAudience التي ينتمي إليها المستخدم
     */
    public function audiencesFor(string $modelType, string $userId): array;
}
