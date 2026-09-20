<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Services;

/**
 * تحويل صفوف خام إلى قائمة مستلمين: تطبيع الأرقام، وفرز المرفوض، وحذف المكرر.
 *
 * تُستدعى مرتين بنفس المدخل: مرة في المعاينة قبل الحفظ، ومرة عند الإنشاء. لأنها
 * دالة خالصة فما يراه المرسِل في المعاينة هو ما يُحفَظ فعلًا، لا تقدير قريب منه.
 */
final readonly class CampaignRecipientListBuilder
{
    public function __construct(
        private CampaignPhoneNormalizer $normalizer,
    ) {}

    /**
     * @param list<array{name: string|null, phone_input: string}> $rows
     * @return array{
     *     accepted: list<array{name: string|null, phone_input: string, phone: string}>,
     *     rejected: list<array{name: string|null, phone_input: string, reason: string}>,
     *     duplicates: int
     * }
     */
    public function build(array $rows): array
    {
        $accepted = [];
        $rejected = [];
        $seen = [];
        $duplicates = 0;

        foreach ($rows as $row) {
            $result = $this->normalizer->normalize($row['phone_input']);

            if ($result['phone'] === null) {
                $rejected[] = [
                    'name' => $row['name'],
                    'phone_input' => $row['phone_input'],
                    'reason' => $result['reason'],
                ];

                continue;
            }

            if (isset($seen[$result['phone']])) {
                $duplicates++;

                continue;
            }

            $seen[$result['phone']] = true;
            $accepted[] = [
                'name' => $row['name'],
                'phone_input' => $row['phone_input'],
                'phone' => $result['phone'],
            ];
        }

        return [
            'accepted' => $accepted,
            'rejected' => $rejected,
            'duplicates' => $duplicates,
        ];
    }
}
