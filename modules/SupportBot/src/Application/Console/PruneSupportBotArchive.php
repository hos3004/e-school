<?php

declare(strict_types=1);

namespace Modules\SupportBot\Application\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\SupportBot\Domain\Models\BotConversation;

/**
 * تقليم أرشيف المحادثات بعد مدة الاحتفاظ.
 *
 * المدة إعداد لا رقم في الكود، والصفر يعني الاحتفاظ بلا حد — وهو الافتراضي حتى
 * يقرر صاحب المنصة مدة بعينها، لأن الأرشيف هو ما تُبنى عليه التقارير الدورية.
 *
 * المعيار آخر نشاط لا حالة الإقفال: جلسة تركها صاحبها ولم يعد تبقى مفتوحة إلى
 * الأبد، ولو اشتُرط الإقفال لما قُلّمت قط. الرسائل تُحذف معها بالقيد المتسلسل.
 */
final class PruneSupportBotArchive extends Command
{
    protected $signature = 'support-bot:prune';

    protected $description = 'حذف محادثات البوت التي تجاوزت مدة الاحتفاظ المضبوطة.';

    public function handle(): int
    {
        $days = (int) config('support_bot.conversation.retention_days', 0);

        if ($days < 1) {
            $this->info((string) __('supportbot::console.prune_disabled'));

            return self::SUCCESS;
        }

        $cutoff = CarbonImmutable::now('UTC')->subDays($days);

        $deleted = BotConversation::query()
            ->whereRaw('COALESCE(last_message_at, started_at) < ?', [$cutoff])
            ->delete();

        $this->info((string) __('supportbot::console.pruned', ['count' => $deleted]));

        return self::SUCCESS;
    }
}
