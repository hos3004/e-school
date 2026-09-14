<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Modules\Notifications\Application\Actions\CancelNotificationAction;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Shared\Support\BusinessRuleViolation;

/**
 * إلغاء رسائل البريد المنتظرة الموجَّهة إلى نطاقات لا تُسلَّم أبدًا.
 *
 * الحسابات المستوردة بلا بريد حقيقي تحمل عناوين على نطاقات محجوزة
 * (‎@…​.invalid). خادم البريد يردّ عليها 450 وهو رمز مؤقت، فيعيد المحرّك
 * المحاولة إلى الأبد ويحجز عمال الطابور عن بقية القنوات. الحارس في
 * MailChannelGateway يمنع تكرار ذلك مستقبلًا، وهذا الأمر ينظّف ما تراكم.
 *
 * لا يلمس رسالة سُلِّمت أو أُلغيت، ولا يحذف صفًا: يمر بفعل الإلغاء المعتمد
 * فتُسجَّل كل حالة في سجل التدقيق بسبب مكتوب، وتبقى قابلة للمراجعة.
 */
final class CancelUndeliverableNotifications extends Command
{
    protected $signature = 'notifications:cancel-undeliverable
                            {--limit=500 : أقصى عدد رسائل تُلغى في التشغيلة الواحدة}
                            {--dry-run : عرض ما سيُلغى دون تنفيذ}';

    protected $description = 'إلغاء رسائل البريد المنتظرة الموجَّهة إلى نطاقات محجوزة لا تُسلَّم.';

    public function handle(CancelNotificationAction $cancel): int
    {
        $domains = $this->domains();

        if ($domains === []) {
            $this->components->warn(__('notifications::messages.undeliverable_domains_unset'));

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $rows = $this->pending($domains, $limit);

        if ($rows->isEmpty()) {
            $this->components->info(__('notifications::messages.undeliverable_none'));

            return self::SUCCESS;
        }

        if ((bool) $this->option('dry-run')) {
            $this->components->info(__('notifications::messages.undeliverable_dry_run', [
                'count' => $rows->count(),
            ]));

            return self::SUCCESS;
        }

        $cancelled = 0;
        $reason = (string) __('notifications::messages.undeliverable_cancel_reason');

        foreach ($rows as $row) {
            try {
                $cancel->execute($row, $reason);
                $cancelled++;
            } catch (BusinessRuleViolation) {
                // التقطها عامل آخر وغيّر حالتها بين الاستعلام والإلغاء.
                continue;
            }
        }

        $this->components->info(__('notifications::messages.undeliverable_cancelled', [
            'count' => $cancelled,
        ]));

        return self::SUCCESS;
    }

    /**
     * @param list<string> $domains
     * @return Collection<int, NotificationOutbox>
     */
    private function pending(array $domains, int $limit)
    {
        return NotificationOutbox::query()
            ->where('status', OutboxStatus::Queued)
            ->where('channel', Channel::Email->value)
            ->whereIn('user_id', function ($query) use ($domains): void {
                $query->select('id')->from('users');
                $query->where(function ($scope) use ($domains): void {
                    foreach ($domains as $domain) {
                        $scope->orWhere('email', 'like', '%@'.$domain)
                            ->orWhere('email', 'like', '%.'.$domain);
                    }
                });
            })
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->get();
    }

    /**
     * النطاقات المحجوزة كما في الإعداد.
     *
     * تُقبل أحرف النطاقات وحدها: القيمة تدخل في نمط LIKE، فحرف بدل واحد في
     * الإعداد كان سيوسّع الاستعلام إلى عناوين حقيقية ويلغي رسائلها.
     *
     * @return list<string>
     */
    private function domains(): array
    {
        $domains = [];

        foreach ((array) config('notifications.channels.email.undeliverable_domains', []) as $domain) {
            if (!is_string($domain)) {
                continue;
            }

            $domain = mb_strtolower(trim($domain, '. '));

            if ($domain !== '' && preg_match('/^[a-z0-9.-]+$/', $domain) === 1) {
                $domains[] = $domain;
            }
        }

        return array_values(array_unique($domains));
    }
}
