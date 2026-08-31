<?php

declare(strict_types=1);

namespace Modules\Guardians\Domain\Contracts;

use Modules\Guardians\Application\Queries\GuardianSummary;

/**
 * قراءة عامة من موديول Guardians — العقد الوحيد المسموح لموديولات أخرى.
 *
 * تُرجع DTOs فقط، لا Eloquent models ولا جداول.
 */
interface GuardianQuery
{
    /**
     * الوصي الأساسي الموثّق للطالب، إن وُجد.
     */
    public function primaryGuardianForStudent(string $studentProfileId): ?GuardianSummary;

    /**
     * كل أوصياء الطالب.
     *
     * @return list<GuardianSummary>
     */
    public function guardiansForStudent(string $studentProfileId): array;

    /**
     * خيارات الأوصياء المتاحين داخل مؤسسة (profile_id => تسمية عرض).
     *
     * @return array<string, string>
     */
    public function guardianOptions(string $organizationId, string $search = ''): array;

    /**
     * اسم عرض لوصي محدد داخل مؤسسة، إن وُجد.
     */
    public function guardianLabel(string $organizationId, string $guardianProfileId): ?string;

    /**
     * هل يحق لهذا المستخدم (بصفته وصيًا موثّقًا) التصرف باسم الطالب؟
     * يراعي guardians.links.require_verification_for_acting و can_act_for.
     */
    public function userCanActForStudent(string $userId, string $studentProfileId): bool;
}
