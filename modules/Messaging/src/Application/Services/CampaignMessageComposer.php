<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Services;

/**
 * تركيب نص رسالة الحملة لمستلم بعينه.
 *
 * الرمز يُستبدل باسم المستلم كما ورد في قائمته. مستلم بلا اسم يُستبدل رمزه
 * بفراغ ثم تُنظَّف المسافات المزدوجة والمسافة قبل علامة الترقيم، فلا تصل رسالة
 * تقول «أهلاً  ،» ولا رسالة تحمل الرمز نفسه ظاهرًا للمستلم.
 */
final class CampaignMessageComposer
{
    public function compose(string $body, ?string $name): string
    {
        $value = $name === null ? '' : trim($name);

        /** @var list<string> $placeholders */
        $placeholders = (array) config('messaging.campaigns.placeholders', []);

        $text = str_replace($placeholders, $value, $body);

        if ($value !== '') {
            return $text;
        }

        $text = preg_replace('/[ \t]{2,}/u', ' ', $text) ?? $text;
        $text = preg_replace('/[ \t]+([،,.:!؟?])/u', '$1', $text) ?? $text;

        return trim($text);
    }
}
