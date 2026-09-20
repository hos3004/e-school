<?php

declare(strict_types=1);

use Modules\Messaging\Application\Services\CampaignMessageComposer;
use Modules\Messaging\Application\Services\CampaignPhoneNormalizer;
use Modules\Messaging\Application\Services\CampaignRecipientListBuilder;
use Modules\Messaging\Application\Services\CampaignRecipientListParser;

function campaignNormalizer(): CampaignPhoneNormalizer
{
    return new CampaignPhoneNormalizer;
}

it('keeps a number already written in international form', function (): void {
    expect(campaignNormalizer()->normalize('+201012345678')['phone'])->toBe('+201012345678');
});

it('turns a leading 00 into +', function (): void {
    expect(campaignNormalizer()->normalize('00201012345678')['phone'])->toBe('+201012345678');
});

it('adds the missing + to a number that already carries its country code', function (): void {
    expect(campaignNormalizer()->normalize('201012345678')['phone'])->toBe('+201012345678');
});

it('strips spaces, dashes and brackets before judging a number', function (): void {
    expect(campaignNormalizer()->normalize(' +20 (10) 1234-5678 ')['phone'])->toBe('+201012345678');
});

it('reads eastern arabic digits', function (): void {
    expect(campaignNormalizer()->normalize('+٢٠١٠١٢٣٤٥٦٧٨')['phone'])->toBe('+201012345678');
});

/*
 * القاعدة التي طلبها صاحب المدرسة صراحةً: طلابه من جنسيات مختلفة، فرقم محلي
 * بصفر واحد لا يُفترض له بلد. تخمين مصر كان سيرسل الرسالة إلى شخص آخر تمامًا
 * يحمل الرقم نفسه في بلد آخر.
 */
it('refuses a bare local number instead of guessing its country', function (): void {
    $result = campaignNormalizer()->normalize('01012345678');

    expect($result['phone'])->toBeNull()
        ->and($result['reason'])->toBe('phone_missing_country_code');
});

it('refuses a number holding letters', function (): void {
    expect(campaignNormalizer()->normalize('call me 20101')['reason'])->toBe('phone_invalid_characters');
});

it('refuses a number that is too short to be real', function (): void {
    expect(campaignNormalizer()->normalize('+2010')['reason'])->toBe('phone_invalid_format');
});

it('reads a pasted list of names and numbers', function (): void {
    $rows = (new CampaignRecipientListParser)->fromText(
        "أحمد, +201012345678\nسارة; 00201112223344\n+201234567890",
    );

    expect($rows)->toHaveCount(3)
        ->and($rows[0])->toBe(['name' => 'أحمد', 'phone_input' => '+201012345678'])
        ->and($rows[1]['name'])->toBe('سارة')
        ->and($rows[2]['name'])->toBeNull();
});

it('reads a line whose number simply trails the name', function (): void {
    $rows = (new CampaignRecipientListParser)->fromText('أحمد محمد +201012345678');

    expect($rows[0])->toBe(['name' => 'أحمد محمد', 'phone_input' => '+201012345678']);
});

it('skips a header row', function (): void {
    $rows = (new CampaignRecipientListParser)->fromText("الاسم,الرقم\nأحمد,+201012345678");

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['name'])->toBe('أحمد');
});

/*
 * ترتيب العمودين لا يُفرض على المرسِل: ملف رقمه أولًا يُقرأ كما يُقرأ العكس.
 */
it('reads the columns in either order', function (): void {
    $rows = (new CampaignRecipientListParser)->fromText('+201012345678, أحمد');

    expect($rows[0])->toBe(['name' => 'أحمد', 'phone_input' => '+201012345678']);
});

it('drops a number repeated in the list', function (): void {
    $list = (new CampaignRecipientListBuilder(campaignNormalizer()))->build([
        ['name' => 'أحمد', 'phone_input' => '+201012345678'],
        ['name' => 'أحمد مرة أخرى', 'phone_input' => '00201012345678'],
        ['name' => 'سارة', 'phone_input' => '+201112223344'],
    ]);

    expect($list['accepted'])->toHaveCount(2)
        ->and($list['duplicates'])->toBe(1);
});

it('separates the numbers needing review from the usable ones', function (): void {
    $list = (new CampaignRecipientListBuilder(campaignNormalizer()))->build([
        ['name' => 'أحمد', 'phone_input' => '+201012345678'],
        ['name' => 'سارة', 'phone_input' => '01112223344'],
    ]);

    expect($list['accepted'])->toHaveCount(1)
        ->and($list['rejected'])->toHaveCount(1)
        ->and($list['rejected'][0]['reason'])->toBe('phone_missing_country_code')
        ->and($list['rejected'][0]['name'])->toBe('سارة');
});

it('puts the recipient name where the placeholder stands', function (): void {
    config(['messaging.campaigns.placeholders' => ['{الاسم}', '{name}']]);

    expect((new CampaignMessageComposer)->compose('أهلًا {الاسم}، الدورة بدأت', 'أحمد'))
        ->toBe('أهلًا أحمد، الدورة بدأت');
});

/*
 * مستلم بلا اسم لا تصله رسالة تحمل الرمز نفسه، ولا مسافة معلّقة قبل الفاصلة.
 */
it('cleans up the sentence when the recipient has no name', function (): void {
    config(['messaging.campaigns.placeholders' => ['{الاسم}', '{name}']]);

    expect((new CampaignMessageComposer)->compose('أهلًا {الاسم}، الدورة بدأت', null))
        ->toBe('أهلًا، الدورة بدأت');
});
