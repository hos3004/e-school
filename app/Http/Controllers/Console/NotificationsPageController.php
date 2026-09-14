<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Domain\Models\NotificationTemplate;

/**
 * مركز الرسائل في الكونسول: الوارد، والتأليف الجماعي، والقوالب، والمجدول.
 *
 * كان المسار صفحة Inertia بلا متحكّم تعرض الوارد فقط. صار يحمل أدوات الإدارة
 * لأن الكونسول هو لوحة الإدارة الفعلية، ولا يصح أن تعيش إدارة القوالب في
 * لوحة ثانية لا يفتحها المستخدمون.
 */
final class NotificationsPageController extends Controller
{
    public function __invoke(Request $request, ConsoleContext $context): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $organizationId = (string) $user->getAttribute('organization_id');
        $canSend = (bool) $user->can('notifications.outbox.create');
        $canManageTemplates = (bool) $user->can('settings.manage');

        return Inertia::render('Console/Notifications', [
            'messaging' => $canSend ? $this->messaging() : null,
            'templates' => $canManageTemplates ? $this->templates($organizationId) : [],
            'templateLocales' => $this->locales(),
            'templateChannels' => $this->channelNames(),
            'scheduled' => $canSend ? $this->scheduled($organizationId) : [],
            'abilities' => [
                'send' => $canSend,
                'templates' => $canManageTemplates,
            ],
            'templateUrls' => $canManageTemplates ? [
                'store' => route('console.notification-templates.store'),
            ] : null,
            'displayTimezone' => (string) $context->forRequest($request)['timezone'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function messaging(): array
    {
        $enabled = $this->channelNames();

        return [
            'sendUrl' => route('console.messages.audience'),
            'targetsUrl' => route('console.messages.targets'),
            'templatesUrl' => route('console.messages.templates'),
            'channels' => $enabled,
            'defaultChannel' => in_array('whatsapp', $enabled, true)
                ? 'whatsapp'
                : (string) ($enabled[0] ?? 'in_app'),
            'fixedTarget' => null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function templates(string $organizationId): array
    {
        return NotificationTemplate::query()
            ->visibleToOrganization($organizationId)
            ->orderBy('event_key')
            ->orderBy('channel')
            ->orderBy('locale')
            ->get()
            ->map(static fn (NotificationTemplate $template): array => [
                'id' => $template->id,
                'event_key' => $template->event_key,
                'channel' => $template->channel,
                'locale' => $template->locale,
                'subject' => $template->subject,
                'body' => $template->body,
                'provider_template_name' => $template->provider_template_name,
                'parameters' => $template->parameters,
                'is_active' => $template->is_active,
                // القالب العام مرجع مشترك: يظهر للقراءة ولا تُعرض أزرار تعديله.
                'is_global' => $template->isGlobal(),
                'updateUrl' => $template->isGlobal()
                    ? null
                    : route('console.notification-templates.update', ['template' => $template->id]),
                'deleteUrl' => $template->isGlobal()
                    ? null
                    : route('console.notification-templates.destroy', ['template' => $template->id]),
            ])
            ->values()
            ->all();
    }

    /**
     * الرسائل المجدولة التي لم يحن موعدها بعد.
     *
     * @return list<array<string, mixed>>
     */
    private function scheduled(string $organizationId): array
    {
        return NotificationOutbox::query()
            ->where('organization_id', $organizationId)
            ->where('status', OutboxStatus::Queued)
            ->where('scheduled_for', '>', CarbonImmutable::now('UTC'))
            ->orderBy('scheduled_for')
            ->limit((int) config('notifications.admin_hub.max_items', 100))
            ->get(['id', 'event_name', 'channel', 'category', 'scheduled_for', 'subject', 'locale'])
            ->map(static fn (NotificationOutbox $row): array => [
                'id' => $row->id,
                'event_name' => $row->event_name,
                'channel' => $row->channel instanceof \BackedEnum
                    ? $row->channel->value
                    : (string) $row->channel,
                'category' => $row->category,
                'scheduled_for' => $row->scheduled_for?->toIso8601String(),
                'subject' => is_array($row->subject)
                    ? ($row->subject[$row->locale] ?? reset($row->subject) ?: null)
                    : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function channelNames(): array
    {
        return array_keys(array_filter(
            (array) config('notifications.channels', []),
            static fn (mixed $settings, string $name): bool => is_array($settings)
                && (bool) ($settings['enabled'] ?? false),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * @return list<string>
     */
    private function locales(): array
    {
        return array_values(array_filter(
            (array) config('notifications.localization.supported', ['ar', 'en']),
            static fn (mixed $locale): bool => is_string($locale) && $locale !== '',
        ));
    }
}
