<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\Contracts;

use Modules\Groups\Domain\ValueObjects\DraftGroupData;

/**
 * إنشاء مجموعة مسودة من خارج الموديول دون لمس نماذجه.
 *
 * الحاجة إليه جاءت من التسكين الجماعي: جذر التركيب ينشئ المجموعة ويربط
 * البرنامج ويسكّن الطلاب في معاملة واحدة، ولا يجوز أن يعرف `Group` ولا
 * `GroupProgram`. التنفيذ داخل الموديول يفوّض إلى الـActions القائمة.
 */
interface GroupProvisioningGateway
{
    /**
     * إنشاء مجموعة «قيد التخطيط» وربط البرنامج بها.
     *
     * لا تُطلب هنا السعة ولا المعلم ولا المواعيد — تبقى مؤجَّلة وتُستوفى
     * قبل التفعيل. الرمز يُولَّد داخليًا من `EntityCodeGenerator`.
     *
     * @param array<string, string> $name اسم المجموعة بحسب اللغة
     */
    public function createDraft(
        string $organizationId,
        array $name,
        string $programId,
        string $timezone,
        string $reason,
        ?string $actorId = null,
    ): DraftGroupData;
}
