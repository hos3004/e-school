<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Console\Support\MessagingChannelOptions;
use App\Http\Controllers\Controller;
use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\Domain\Models\User;
use Modules\Integrations\Domain\Contracts\GreenApiConnections;
use Modules\Notifications\Application\Actions\CancelNotificationAction;
use Modules\Notifications\Application\Services\ManualNotificationRecipientResolver;
use Modules\Notifications\Domain\Enums\ManualAudience;
use Modules\Notifications\Domain\Enums\ManualRecipientType;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Domain\Models\NotificationTemplate;
use Shared\Support\BusinessRuleViolation;

/**
 * مركز واتساب: حالة القناة ومفتاح إيقافها وقوالبها وسجلّها ومحاكاة الإرسال.
 *
 * القسم يجمع ما يخص واتساب في مكان واحد ولا يلغي مداخله الأخرى — زر المراسلة
 * في بروفايل الطالب وصفحة الرسائل يبقيان كما هما ويمرّان بنفس المحرّك، فما
 * يظهر هنا هو الحقيقة نفسها لا نسخة ثانية منها.
 */
final class WhatsappController extends Controller
{
    private const CHANNEL = 'whatsapp';

    public function __construct(
        private readonly GreenApiConnections $connections,
        private readonly ManualNotificationRecipientResolver $recipients,
        private readonly ConsoleContext $context,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $organizationId = (string) $user->getAttribute('organization_id');
        $canSend = (bool) $user->can('notifications.outbox.create');
        $canManageTemplates = (bool) $user->can('settings.manage');
        $canToggle = (bool) $user->can('integrations.connection.update');

        return Inertia::render('Console/Whatsapp', [
            'connection' => $this->connections->view($organizationId),
            'channelEnabled' => $this->connections->isChannelEnabled($organizationId),
            'stats' => $this->stats($organizationId),
            'recent' => $this->recent($organizationId),
            'templates' => $canManageTemplates ? $this->templates($organizationId) : [],
            'messaging' => $canSend ? $this->messaging() : null,
            'automaticNotice' => (string) __('integrations::whatsapp.automatic_notice'),
            'abilities' => [
                'send' => $canSend,
                'templates' => $canManageTemplates,
                'toggle' => $canToggle,
                'settings' => $canManageTemplates,
            ],
            'urls' => [
                'toggle' => route('console.whatsapp.toggle'),
                'preview' => route('console.whatsapp.preview'),
                'settings' => route('console.settings.green-api'),
                'webhook' => route('console.settings.green-api.webhook'),
                'notifications' => route('console.notifications'),
            ],
            'displayTimezone' => (string) $this->context->forRequest($request)['timezone'],
        ]);
    }

    /**
     * مفتاح الطوارئ. الإيقاف يمنع كتابة أي رسالة واتساب جديدة ويُلغي ما ينتظر
     * في الطابور، فلا تخرج رسائل بعد ضغط الزر.
     */
    public function toggle(Request $request, CancelNotificationAction $cancel): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);
        abort_unless($user->can('integrations.connection.update'), 403);

        $data = $request->validate([
            'active' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $organizationId = (string) $user->getAttribute('organization_id');
        $actorId = (string) $user->getAuthIdentifier();
        $active = (bool) $data['active'];
        $reason = (string) $data['reason'];

        $this->connections->setChannelActive($organizationId, $active, $actorId, $reason);

        $cancelled = 0;

        if (!$active) {
            $pending = NotificationOutbox::query()
                ->where('organization_id', $organizationId)
                ->where('channel', self::CHANNEL)
                ->where('status', OutboxStatus::Queued)
                ->get();

            foreach ($pending as $row) {
                try {
                    $cancel->execute($row, $reason, $actorId);
                    $cancelled++;
                } catch (BusinessRuleViolation) {
                    // سبقها المرسِل إلى الالتقاط — لا يصح أن يفشل المفتاح لأجلها.
                    continue;
                }
            }
        }

        return back()->with('success', $active
            ? __('console_whatsapp.toggle.resumed')
            : __('console_whatsapp.toggle.paused', ['count' => $cancelled]));
    }

    /**
     * محاكاة: النص النهائي كما سيصل، ومن سيستقبله بالاسم والرقم، بلا إرسال.
     *
     * هذه الخطوة هي الفرق بين «أرسلت لـ55 شخصًا بالخطأ» و«رأيت الـ55 قبل
     * الضغط». لا تكتب شيئًا ولا تمس الطابور.
     */
    public function preview(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);
        abort_unless($user->can('notifications.outbox.create'), 403);

        $data = $request->validate([
            'recipient_type' => ['required', 'string'],
            'target_id' => ['required', 'string'],
            'audience' => ['required', 'string'],
            'subject' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $organizationId = (string) $user->getAttribute('organization_id');

        try {
            $resolution = $this->recipients->resolve(
                $organizationId,
                ManualRecipientType::from((string) $data['recipient_type']),
                (string) $data['target_id'],
                ManualAudience::from((string) $data['audience']),
            );
        } catch (BusinessRuleViolation $error) {
            throw ValidationException::withMessages(['target_id' => $error->getMessage()]);
        }

        $subject = trim((string) ($data['subject'] ?? ''));
        $body = trim((string) $data['body']);
        $text = $subject !== '' && $subject !== $body
            ? '*'.$subject.'*'.PHP_EOL.PHP_EOL.$body
            : $body;

        $accounts = User::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $resolution->userIds)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'status']);

        $reachable = [];
        $unreachable = [];

        foreach ($accounts as $account) {
            $phone = $account->phone === null ? '' : trim((string) $account->phone);
            $entry = [
                'id' => (string) $account->getKey(),
                'name' => (string) $account->name,
                'phone' => $phone === '' ? null : $phone,
            ];

            if ($phone === '') {
                $entry['reason'] = (string) __('console_whatsapp.preview.no_phone');
                $unreachable[] = $entry;

                continue;
            }

            $reachable[] = $entry;
        }

        return response()->json([
            'label' => $resolution->label,
            // الرسالة اليدوية بلا وسم آلي — الوسم للرسائل التي يولّدها النظام.
            'text' => $text,
            'resolved_count' => $resolution->count(),
            'reachable' => $reachable,
            'unreachable' => $unreachable,
            'channel_enabled' => $this->connections->isChannelEnabled($organizationId),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function stats(string $organizationId): array
    {
        $since = CarbonImmutable::now('UTC')->subDay();

        $counts = NotificationOutbox::query()
            ->where('organization_id', $organizationId)
            ->where('channel', self::CHANNEL)
            ->where('created_at', '>=', $since)
            ->groupBy('status')
            ->select('status', DB::raw('count(*) as total'))
            ->pluck('total', 'status')
            ->all();

        $result = ['queued' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0, 'suppressed' => 0];

        foreach ($counts as $status => $total) {
            $key = $status instanceof BackedEnum ? (string) $status->value : (string) $status;
            $result[$key] = (int) $total;
        }

        return $result;
    }

    /**
     * آخر رسائل القناة، وكل سطر يقول هل هي تلقائية من النظام أم كتبها موظف.
     *
     * @return list<array<string, mixed>>
     */
    private function recent(string $organizationId): array
    {
        return NotificationOutbox::query()
            ->where('organization_id', $organizationId)
            ->where('channel', self::CHANNEL)
            ->orderByDesc('created_at')
            ->limit((int) config('notifications.admin_hub.max_items', 100))
            ->get()
            ->map(function (NotificationOutbox $row): array {
                $payload = (array) ($row->payload ?? []);

                return [
                    'id' => (string) $row->getKey(),
                    'event_name' => (string) $row->event_name,
                    'category' => (string) $row->category,
                    'status' => $row->status instanceof BackedEnum
                        ? (string) $row->status->value
                        : (string) $row->status,
                    'automatic' => ($payload['manual'] ?? false) !== true,
                    'created_at' => $row->created_at?->toIso8601String(),
                    'sent_at' => $row->sent_at?->toIso8601String(),
                    'failure_reason' => $row->failure_reason,
                    'subject' => $this->localized($row->subject, (string) $row->locale),
                    'body' => $this->localized($row->body, (string) $row->locale),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * قوالب واتساب وحدها — باقي القنوات لها مكانها في مركز الرسائل.
     *
     * @return list<array<string, mixed>>
     */
    private function templates(string $organizationId): array
    {
        return NotificationTemplate::query()
            ->visibleToOrganization($organizationId)
            ->where('channel', self::CHANNEL)
            ->orderBy('event_key')
            ->orderBy('locale')
            ->get()
            ->map(static fn (NotificationTemplate $template): array => [
                'id' => (string) $template->getKey(),
                'event_key' => (string) $template->event_key,
                'channel' => (string) $template->channel,
                'locale' => (string) $template->locale,
                'subject' => $template->subject,
                'body' => (string) $template->body,
                'parameters' => $template->parameters,
                'is_active' => (bool) $template->is_active,
                'is_global' => $template->isGlobal(),
                'updateUrl' => $template->isGlobal()
                    ? null
                    : route('console.notification-templates.update', ['template' => $template->getKey()]),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function messaging(): array
    {
        $options = app(MessagingChannelOptions::class);
        $channels = $options->all();

        return [
            'sendUrl' => route('console.messages.audience'),
            'targetsUrl' => route('console.messages.targets'),
            'templatesUrl' => route('console.messages.templates'),
            'channels' => $channels,
            // القسم يخص واتساب، فالقناة المختارة سلفًا هي واتساب لا افتراضي عام.
            'defaultChannel' => self::CHANNEL,
        ];
    }

    private function localized(mixed $values, string $locale): ?string
    {
        if (!is_array($values)) {
            return is_string($values) ? $values : null;
        }

        $value = $values[$locale] ?? null;

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        foreach ($values as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return null;
    }
}
