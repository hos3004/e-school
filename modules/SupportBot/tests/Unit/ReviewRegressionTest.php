<?php

declare(strict_types=1);

use App\Support\SupportBot\PlatformDataSource;
use Modules\SupportBot\Application\Services\MoneyIntentDetector;
use Modules\SupportBot\Application\Services\OutputFilter;
use Modules\SupportBot\Domain\Enums\BotTopic;

/*
| اختبارات انحدار لما كشفته المراجعة المستقلة قبل النشر.
|
| كل حالة هنا كانت تمر قبل الإصلاح. لو عادت إحداها تمر، فقد عاد الخلل.
*/

it('catches an amount whose currency word sits several Arabic words away', function (string $text): void {
    // النافذة كانت بالبايت، فتنكمش في العربية إلى نصف حجمها وتفلت هذه الجمل.
    expect((new OutputFilter)->passes($text))->toBeFalse();
})->with([
    'currency after a clause' => 'رصيدك الحالي 3125 وهو بالجنيه المصري.',
    'currency before a clause' => 'المبلغ بالجنيه المصري: 3125',
    'thousand word between' => 'مستحقاتك ٣١٢٥ ألف جنيه.',
]);

it('catches spelled-out and lowercase amounts', function (string $text): void {
    expect((new OutputFilter)->passes($text))->toBeFalse();
})->with([
    'spelled out' => 'مستحقاتك ثلاثة آلاف جنيه تقريبًا.',
    'lowercase code' => 'your balance is 3125 egp',
    'mixed case code' => 'total 40 Usd',
    'dinar' => 'المبلغ 200 دينار',
]);

it('withholds a pay-relevant count even without any currency', function (string $text): void {
    expect((new OutputFilter)->passes($text))->toBeFalse();
})->with([
    'credited sessions' => 'الحصص المحتسبة لك هذا الشهر 12 حصة.',
    'dues without currency' => 'مستحقاتك هذا الشهر ٣١٢٥.',
]);

it('still lets ordinary times, counts and durations through', function (string $text): void {
    expect((new OutputFilter)->passes($text))->toBeTrue();
})->with([
    'time' => 'حصتك القادمة الساعة 7 مساءً.',
    'count' => 'لديك 3 حصص مجدولة هذا الأسبوع.',
    'duration' => 'مدة الحصة 35 دقيقة.',
    'steps' => 'افتح صفحة المستحقات ثم اختر الشهر، وستجد التفاصيل في الخطوة 2.',
]);

it('keeps filtering when the configured marker list is empty', function (): void {
    // إعداد ناقص في كاش الإنتاج كان يعطّل الفلتر صامتًا.
    config(['support_bot.output_filter.currency_markers' => []]);

    expect((new OutputFilter)->passes('الرصيد 3125 جنيه'))->toBeFalse();
});

it('detects money intent in the raw question, whatever the classifier said', function (string $message, BotTopic $expected): void {
    expect((new MoneyIntentDetector)->detect($message))->toBe($expected);
})->with([
    'dues' => ['كم مستحقاتي هذا الشهر؟', BotTopic::PayrollDues],
    'with diacritics' => ['كم مُسْتَحَقَّاتي؟', BotTopic::PayrollDues],
    'with tatweel' => ['كم مستحـقاتي؟', BotTopic::PayrollDues],
    'colloquial' => ['هقبض امتى؟', BotTopic::PayrollDues],
    'hamza form' => ['كم اخذ في الشهر؟ كم آخذ', BotTopic::PayrollDues],
    'credited sessions' => ['كام حصة محسوبة ليا؟', BotTopic::SessionCount],
    'fees' => ['كم الرسوم؟', BotTopic::Billing],
]);

it('does not mistake Quranic reward for pay', function (string $message): void {
    // «أجر» في هذا السياق ثواب لا راتب.
    expect((new MoneyIntentDetector)->detect($message))->toBeNull();
})->with([
    'reward of memorising' => 'ما أجر حفظ سورة الملك؟',
    'reward of reciting' => 'هل للقراءة أجر مضاعف؟',
    'schedule' => 'متى حصتي القادمة؟',
    'joining' => 'كيف أدخل الفصل؟',
]);

it('reports attendance as a percentage, not a rounded fraction', function (): void {
    // attendanceRate يعيد 0.8571؛ تقريبها مباشرة كان يعطي «1%».
    expect(PlatformDataSource::percent(0.8571))->toBe('86%')
        ->and(PlatformDataSource::percent(0.4))->toBe('40%')
        ->and(PlatformDataSource::percent(1.0))->toBe('100%')
        ->and(PlatformDataSource::percent(0.0))->toBe('0%');
});
