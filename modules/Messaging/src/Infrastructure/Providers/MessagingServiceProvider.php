<?php

declare(strict_types=1);

namespace Modules\Messaging\Infrastructure\Providers;

use Modules\Messaging\Application\Policies\ClassWallCommentPolicy;
use Modules\Messaging\Application\Policies\ClassWallPostPolicy;
use Modules\Messaging\Application\Policies\ConversationPolicy;
use Modules\Messaging\Application\Policies\MessagePolicy;
use Modules\Messaging\Application\Policies\WhatsappInboundPolicy;
use Modules\Messaging\Domain\Models\ClassWallComment;
use Modules\Messaging\Domain\Models\ClassWallPost;
use Modules\Messaging\Domain\Models\Conversation;
use Modules\Messaging\Domain\Models\Message;
use Modules\Messaging\Domain\Models\WhatsappInbound;
use Shared\Module\BaseModuleServiceProvider;
use Shared\Support\DatabaseTransaction;
use Shared\Support\Transaction;

final class MessagingServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Messaging';
    }

    protected function listeners(): array
    {
        return [
            // أحداث الموديول منشورة للمستقبلين الآخرين — لا مستمعين داخليين حتى الآن.
        ];
    }

    protected function policies(): array
    {
        return [
            Conversation::class => ConversationPolicy::class,
            Message::class => MessagePolicy::class,
            ClassWallPost::class => ClassWallPostPolicy::class,
            ClassWallComment::class => ClassWallCommentPolicy::class,
            WhatsappInbound::class => WhatsappInboundPolicy::class,
        ];
    }

    protected function bindings(): array
    {
        return [
            Transaction::class => DatabaseTransaction::class,
        ];
    }
}
