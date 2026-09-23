<?php

declare(strict_types=1);

namespace Modules\SupportBot\Infrastructure\Persistence;

use Modules\SupportBot\Domain\Contracts\SupportBotDataSource;
use Modules\SupportBot\Domain\Enums\BotTopic;

/**
 * مصدر بيانات لا يعيد شيئًا — الارتباط الافتراضي للعقد.
 *
 * موجود ليكون **الفشل مغلقًا**: التنفيذ الحقيقي يعيش في app/ لأنه يركّب قراءات
 * من موديولات عدة ويفحص صلاحيات كل منها. لو لم يُسجَّل ذلك الارتباط — نسيانًا أو
 * أثناء اختبار — فالبديل هنا يجعل البوت يجيب من المعرفة العامة وحدها.
 *
 * البديل الآخر كان استثناءً عند غياب الارتباط، وهو أسوأ: بوت يعتذر عن كل سؤال
 * أسوأ من بوت يجيب عن أسئلة المنصة ولا يعرف جدولك.
 */
final readonly class NullDataSource implements SupportBotDataSource
{
    /**
     * @return list<array{label: string, value: string}>
     */
    public function factsFor(string $organizationId, string $userId, BotTopic $topic, string $locale): array
    {
        return [];
    }
}
