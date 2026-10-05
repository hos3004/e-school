<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Services;

use Modules\Groups\Application\Actions\AttachProgramAction;
use Modules\Groups\Application\Actions\CreateGroupAction;
use Modules\Groups\Domain\Contracts\GroupProvisioningGateway;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\ValueObjects\DraftGroupData;
use Shared\Codes\EntityCodeGenerator;
use Shared\Support\Transaction;

/**
 * إنشاء المسودة عبر الـActions القائمة لا بكتابة مباشرة.
 *
 * `CreateGroupAction` هو من يضع الحالة `Planning` وينشر `GroupCreated`،
 * و`AttachProgramAction` هو من يتحقق أن البرنامج يخص المؤسسة. هذه الخدمة
 * تجمعهما خلف عقد واحد ولا تضيف منطق أعمال جديدًا.
 */
final readonly class GroupProvisioningService implements GroupProvisioningGateway
{
    public function __construct(
        private CreateGroupAction $createGroup,
        private AttachProgramAction $attachProgram,
        private EntityCodeGenerator $codes,
        private Transaction $transaction,
    ) {}

    public function createDraft(
        string $organizationId,
        array $name,
        string $programId,
        string $timezone,
        string $reason,
        ?string $actorId = null,
    ): DraftGroupData {
        return $this->transaction->run(function () use (
            $organizationId,
            $name,
            $programId,
            $timezone,
            $reason,
            $actorId,
        ): DraftGroupData {
            /*
             * السعة وتاريخ البدء غائبان عمدًا: القاعدة تسمح بغيابهما ما دامت
             * الحالة `planning`، وقيد `groups_activation_completeness_check`
             * يمنع تسربهما فارغين إلى أي حالة أخرى.
             */
            $group = $this->createGroup->execute(
                [
                    'organization_id' => $organizationId,
                    'code' => $this->codes->next('group'),
                    'name' => $name,
                    'timezone' => $timezone,
                ],
                $actorId,
                $reason,
            );

            $this->attachProgram->execute($group, $programId, $actorId, $reason);

            return $this->toData($group, $programId);
        });
    }

    private function toData(Group $group, string $programId): DraftGroupData
    {
        return new DraftGroupData(
            groupId: (string) $group->getKey(),
            organizationId: (string) $group->organization_id,
            code: (string) $group->code,
            name: is_array($group->name) ? $group->name : [],
            status: $group->status->value,
            programId: $programId,
        );
    }
}
