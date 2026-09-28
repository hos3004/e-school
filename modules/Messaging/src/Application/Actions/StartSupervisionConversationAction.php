<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Actions;

use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Messaging\Domain\Enums\ConversationType;
use Modules\Messaging\Domain\Models\Conversation;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * "راسل الإشراف": قناة ثابتة من المعلم (أو أي فاعل message.send) إلى كل من
 * يحمل حاليًا أحد الأدوار في config('messaging.supervision.recipient_role_names') —
 * دور واحد ('platform_admin') في هذه المرحلة، توسيعه لاحقًا إلى صلاحية
 * قابلة للمنح فرديًا هو مجرد تبديل هذا الإعداد يوم يتقرر، لا تعديل هنا.
 *
 * محادثة واحدة متكررة لكل فاعل (related_type='supervision', related_id=الفاعل)
 * بدل محادثة جديدة كل مرة — مطابقة لسلوك "تذكرة دعم" متصلة.
 */
final readonly class StartSupervisionConversationAction
{
    private const string RELATED_TYPE = 'supervision';

    public function __construct(
        private Transaction $transaction,
        private AccessControlQuerier $accessControl,
        private UserAccountDirectory $accounts,
        private CreateConversationAction $createConversation,
        private SendMessageAction $sendMessage,
    ) {}

    public function execute(string $organizationId, string $actorUserId, string $body): Conversation
    {
        $roleNames = (array) config('messaging.supervision.recipient_role_names', []);
        $modelType = (string) config('auth.providers.users.model');

        $candidateIds = $this->accessControl->modelIdsForRoleNames($modelType, $roleNames);
        $recipientIds = array_keys($this->accounts->findMany($organizationId, $candidateIds));

        if ($recipientIds === []) {
            throw BusinessRuleViolation::make(
                'messaging.supervision_unavailable',
                'messaging::errors.supervision_unavailable',
            );
        }

        return $this->transaction->run(function () use ($organizationId, $actorUserId, $recipientIds, $body): Conversation {
            $conversation = Conversation::query()
                ->forOrganization($organizationId)
                ->where('related_type', self::RELATED_TYPE)
                ->where('related_id', $actorUserId)
                ->where('created_by', $actorUserId)
                ->first();

            if ($conversation === null) {
                $conversation = $this->createConversation->execute(
                    organizationId: $organizationId,
                    creatorUserId: $actorUserId,
                    type: ConversationType::Group,
                    subject: (string) config('messaging.supervision.conversation_subject'),
                    participantUserIds: $recipientIds,
                    relatedType: self::RELATED_TYPE,
                    relatedId: $actorUserId,
                );
            }

            $this->sendMessage->execute(
                conversation: $conversation,
                senderUserId: $actorUserId,
                body: $body,
            );

            return $conversation->refresh();
        });
    }
}
