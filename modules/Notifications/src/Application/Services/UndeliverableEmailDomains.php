<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Services;

/**
 * النطاقات المحجوزة التي لا تُسلَّم إليها رسالة أبدًا (RFC 2606 و RFC 6761).
 *
 * الحسابات المستوردة بلا بريد حقيقي تحمل عناوين على هذه النطاقات. خادم
 * البريد يردّ عليها برمز مؤقت (450) فتُعاد المحاولة بلا نهاية ما لم يُرفض
 * العنوان قبل الاتصال. تعيش القاعدة هنا لا داخل البوابة كي يستعملها أيضًا
 * مَن يبني واجهة الاختيار: عرض «بريد إلكتروني» خيارًا صالحًا لحساب عنوانه
 * وهمي يعني رسالة تفشل بعد الإرسال بدل تحذير قبله.
 */
final readonly class UndeliverableEmailDomains
{
    public function isUndeliverable(?string $email): bool
    {
        if (!is_string($email) || trim($email) === '') {
            return true;
        }

        $domain = mb_strtolower(substr((string) strrchr($email, '@'), 1));

        if ($domain === '') {
            return true;
        }

        foreach ($this->reserved() as $reserved) {
            if ($domain === $reserved || str_ends_with($domain, '.'.$reserved)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function reserved(): array
    {
        $domains = [];

        foreach ((array) config('notifications.channels.email.undeliverable_domains', []) as $domain) {
            if (!is_string($domain)) {
                continue;
            }

            $domain = mb_strtolower(trim($domain, '. '));

            if ($domain !== '') {
                $domains[] = $domain;
            }
        }

        return array_values(array_unique($domains));
    }
}
