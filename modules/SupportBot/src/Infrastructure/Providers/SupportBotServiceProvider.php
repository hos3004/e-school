<?php

declare(strict_types=1);

namespace Modules\SupportBot\Infrastructure\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Modules\SupportBot\Domain\Contracts\SupportBotDataSource;
use Modules\SupportBot\Infrastructure\Persistence\NullDataSource;
use Shared\Module\BaseModuleServiceProvider;

final class SupportBotServiceProvider extends BaseModuleServiceProvider
{
    /** اسم محدِّد المعدّل الذي تعلّقه مسارات المحادثة. */
    public const string ASK_LIMITER = 'support-bot-ask';

    protected function moduleName(): string
    {
        return 'SupportBot';
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [
            /*
             * الافتراضي لا يعيد بيانات. التنفيذ الحقيقي يعيش في app/ ويُسجَّل
             * هناك، لأنه يركّب قراءات من موديولات عدة ولا يجوز لموديول أن يعتمد
             * على app/.
             */
            SupportBotDataSource::class => NullDataSource::class,
        ];
    }

    public function boot(): void
    {
        parent::boot();

        /*
         * لا يوجد throttle عام في هذا المشروع، وكل رسالة هنا تكلّف مالًا. الحد
         * لكل مستخدم لا لكل IP: المستخدمون خلف شبكة مدرسة واحدة يشتركون في IP،
         * فالحد بالعنوان كان سيعاقب زملاء من أسرف.
         */
        RateLimiter::for(self::ASK_LIMITER, static function (Request $request): Limit {
            $perMinute = max(1, (int) config('support_bot.limits.messages_per_minute', 8));
            $userId = $request->user()?->getAuthIdentifier();

            /*
             * طلب بلا مستخدم لا يُفترض أن يصل — المسار خلف auth. لكن إن وصل
             * فالحد يشتدّ ولا يسقط: `Limit::none()` هنا كانت ستعني «بلا حد
             * إطلاقًا»، أي أن ثغرة في المصادقة تتحول إلى باب إنفاق مفتوح.
             */
            if ($userId === null) {
                return Limit::perMinute(1)->by('support-bot:anonymous:'.$request->ip());
            }

            return Limit::perMinute($perMinute)->by('support-bot:'.$userId);
        });
    }
}
