<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\SupportBotWriteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Integrations\Domain\Contracts\LlmConnections;
use Modules\Organization\Application\Actions\UpsertOrganizationSetting;
use Modules\Organization\Domain\Contracts\OrganizationSettingQueries;
use Modules\Organization\Domain\Models\Organization;
use Modules\Organization\Domain\Models\OrganizationSetting;
use Modules\SupportBot\Application\Services\UsageAccountant;
use Modules\SupportBot\Domain\Enums\BotAudience;
use Modules\SupportBot\Domain\Enums\BotTopic;
use Modules\SupportBot\Domain\Enums\EntryKind;
use Modules\SupportBot\Domain\Enums\TopicMode;
use Modules\SupportBot\Domain\Models\BotAccountAccess;
use Modules\SupportBot\Domain\Models\BotConversation;
use Modules\SupportBot\Domain\Models\BotEntry;
use Modules\SupportBot\Domain\Models\BotMessage;
use Modules\SupportBot\Domain\Models\BotRule;

/**
 * قسم «The Bot» — كل ما يخص البوت في مكان واحد.
 *
 * التجميع مقصود: نثر أزرار البوت على الشاشات زحم الواجهة في تجربة سابقة، بينما
 * قسم واحد يجعل من يريد ضبط البوت يعرف إلى أين يذهب.
 *
 * كل كتابة هنا تحمل سببًا مكتوبًا وتُقيَّد في سجل التدقيق، على نفس نمط بقية
 * شاشات اللوحة. والمفتاح السرّي للمزوّد لا يُدخَل من هنا إطلاقًا — يُدخَل بسكربت
 * على الخادم، فلا يمر بشاشة يفتحها موظف ولا يُكتب في محادثة.
 */
final class SupportBotConsoleController extends Controller
{
    public function index(
        Request $request,
        ConsoleContext $context,
        LlmConnections $connections,
        OrganizationSettingQueries $settings,
        UsageAccountant $usage,
    ): Response {
        $organizationId = $this->organizationId($request);
        $user = $request->user();

        Gate::authorize('support_bot.manage');

        $canReadArchive = $user->can('support_bot.archive.view');

        return Inertia::render('Console/SupportBot', [
            'console' => $context->forRequest($request),
            'connection' => $connections->view($organizationId),
            'audiences' => $this->audienceState($organizationId, $settings),
            'entries' => $this->entries($organizationId),
            'rules' => $this->rules($organizationId),
            'blockedAccounts' => $this->blockedAccounts($organizationId),
            'usage' => [
                'spentTodayMicroUsd' => $usage->organizationSpendToday($organizationId),
                'dailyCapMicroUsd' => (int) config('support_bot.limits.daily_cost_cap_micro_usd'),
                'messagesPerDay' => (int) config('support_bot.limits.messages_per_day'),
                'messagesPerMinute' => (int) config('support_bot.limits.messages_per_minute'),
            ],
            'vocabulary' => [
                'topics' => array_map(
                    static fn (BotTopic $topic): array => [
                        'value' => $topic->value,
                        'label' => $topic->label(),
                        'money' => $topic->disclosesFigures(),
                    ],
                    BotTopic::cases(),
                ),
                'audiences' => array_map(
                    static fn (BotAudience $audience): array => [
                        'value' => $audience->value,
                        'label' => $audience->label(),
                    ],
                    BotAudience::cases(),
                ),
                'modes' => array_map(
                    static fn (TopicMode $mode): array => [
                        'value' => $mode->value,
                        'label' => $mode->label(),
                    ],
                    TopicMode::cases(),
                ),
                'kinds' => array_map(
                    static fn (EntryKind $kind): array => [
                        'value' => $kind->value,
                        'label' => $kind->label(),
                    ],
                    EntryKind::cases(),
                ),
            ],
            'archive' => $canReadArchive ? $this->conversations($organizationId) : null,
            'abilities' => [
                'manage' => true,
                'readArchive' => $canReadArchive,
            ],
            'urls' => [
                'toggle' => route('console.bot.toggle'),
                'audiences' => route('console.bot.audiences'),
                'entry' => route('console.bot.entry'),
                'rule' => route('console.bot.rule'),
                'access' => route('console.bot.access'),
                'conversation' => route('console.bot.conversation', ['conversation' => '__ID__']),
            ],
        ]);
    }

    /** مفتاح الإيقاف الفوري. */
    public function toggle(SupportBotWriteRequest $request, LlmConnections $connections): RedirectResponse
    {
        Gate::authorize('support_bot.manage');

        $organizationId = $this->organizationId($request);

        $connections->setActive(
            $organizationId,
            $request->boolean('active'),
            (string) $request->user()->getAuthIdentifier(),
            $request->reason(),
        );

        return back()->with('success', __('console_bot.flash.toggled'));
    }

    /** الفئات المفعَّلة — تبدأ بالمعلمين ومشرف الجودة وتتوسّع من هنا بلا نشر. */
    public function audiences(
        SupportBotWriteRequest $request,
        OrganizationSettingQueries $settings,
        UpsertOrganizationSetting $upsert,
        AuditRecorder $audit,
    ): RedirectResponse {
        Gate::authorize('support_bot.manage');

        $organizationId = $this->organizationId($request);

        $selected = array_values(array_filter(
            (array) $request->input('audiences', []),
            static fn (mixed $value): bool => is_string($value)
                && BotAudience::tryFrom($value) instanceof BotAudience,
        ));

        $key = (string) config('support_bot.audiences_setting_key');
        $before = $settings->value($organizationId, $key);

        /*
         * مسار الكتابة الرسمي لموديول المؤسسة لا كتابة يدوية في جدوله: الأخيرة
         * تتجاوز توليد المعرّف (فتنهار أول مرة على قيد NOT NULL) وتتجاوز حدث
         * OrganizationSettingUpdated الذي يستمع إليه غيرنا.
         */
        $organization = Organization::query()->findOrFail($organizationId);
        $setting = $upsert->execute($organization, $key, $selected);

        $audit->record($organizationId, (string) $request->user()->getAuthIdentifier(), 'user',
            'support_bot.audiences_changed', OrganizationSetting::class, (string) $setting->getKey(),
            ['audiences' => $before], ['audiences' => $selected], $request->reason());

        return back()->with('success', __('console_bot.flash.audiences_saved'));
    }

    /**
     * تحرير نص: توجيه أو معرفة أو ردّ معدّ.
     *
     * يكتب دائمًا صفَّ المؤسسة لا الصف العام. الصف العام يبقى كما شُحن مرجعًا
     * يمكن الرجوع إليه، وصف المؤسسة يغطّيه عند القراءة.
     */
    public function entry(SupportBotWriteRequest $request, AuditRecorder $audit): RedirectResponse
    {
        Gate::authorize('support_bot.manage');

        $organizationId = $this->organizationId($request);
        $actorId = (string) $request->user()->getAuthIdentifier();

        $validated = $request->validate([
            'kind' => ['required', 'string', Rule::in(array_column(EntryKind::cases(), 'value'))],
            'key' => ['required', 'string', 'max:128'],
            'locale' => ['required', 'string', Rule::in((array) config('app.supported_locales', ['ar']))],
            'title' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:8000'],
            // موضوع مكتوب خطأً كان يُحفظ ولا يُضمّ إلى أي سياق أبدًا، بلا أي تنبيه.
            'topic' => ['nullable', 'string', Rule::in(array_column(BotTopic::cases(), 'value'))],
            'audiences' => ['array'],
            // فئة مجهولة كانت تُسقط صامتة فتصير القائمة فارغة = «للجميع».
            'audiences.*' => ['string', Rule::in(array_column(BotAudience::cases(), 'value'))],
            'is_active' => ['boolean'],
        ]);

        $existing = BotEntry::query()
            ->where('organization_id', $organizationId)
            ->where('kind', $validated['kind'])
            ->where('key', $validated['key'])
            ->where('locale', $validated['locale'])
            ->first();

        $before = $existing?->only(['body', 'title', 'audiences', 'topic', 'is_active']) ?? [];

        $entry = BotEntry::query()->updateOrCreate(
            [
                'organization_id' => $organizationId,
                'kind' => $validated['kind'],
                'key' => $validated['key'],
                'locale' => $validated['locale'],
            ],
            [
                'title' => $validated['title'] ?? null,
                'body' => $validated['body'],
                'topic' => $validated['topic'] ?? null,
                'audiences' => array_values(array_unique($validated['audiences'] ?? [])),
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'created_by' => $existing === null ? $actorId : $existing->created_by,
                'updated_by' => $actorId,
            ],
        );

        $audit->record($organizationId, $actorId, 'user',
            'support_bot.entry_saved', BotEntry::class, (string) $entry->getKey(),
            $before, $entry->only(['body', 'title', 'audiences', 'topic', 'is_active']), $request->reason());

        return back()->with('success', __('console_bot.flash.entry_saved'));
    }

    /** تحرير صف في مصفوفة الحدود. */
    public function rule(SupportBotWriteRequest $request, AuditRecorder $audit): RedirectResponse
    {
        Gate::authorize('support_bot.manage');

        $organizationId = $this->organizationId($request);
        $actorId = (string) $request->user()->getAuthIdentifier();

        $validated = $request->validate([
            'topic' => ['required', 'string', Rule::in(array_column(BotTopic::cases(), 'value'))],
            'audience' => ['required', 'string', Rule::in(array_column(BotAudience::cases(), 'value'))],
            'mode' => ['required', 'string', Rule::in(array_column(TopicMode::cases(), 'value'))],
            'reply_key' => ['nullable', 'string', 'max:128'],
        ]);

        $topic = BotTopic::tryFrom($validated['topic']);
        $audience = BotAudience::tryFrom($validated['audience']);

        abort_if($topic === null || $audience === null, 422);

        $existing = BotRule::query()
            ->where('organization_id', $organizationId)
            ->where('topic', $topic->value)
            ->where('audience', $audience->value)
            ->first();

        $rule = BotRule::query()->updateOrCreate(
            [
                'organization_id' => $organizationId,
                'topic' => $topic->value,
                'audience' => $audience->value,
            ],
            [
                'mode' => $validated['mode'],
                'reply_key' => $validated['reply_key'] ?? null,
                'is_active' => true,
                'updated_by' => $actorId,
            ],
        );

        $audit->record($organizationId, $actorId, 'user',
            'support_bot.rule_saved', BotRule::class, (string) $rule->getKey(),
            $existing?->only(['mode', 'reply_key']) ?? [], $rule->only(['mode', 'reply_key']), $request->reason());

        return back()->with('success', __('console_bot.flash.rule_saved'));
    }

    /** فتح البوت أو إغلاقه لحساب بعينه. */
    public function access(SupportBotWriteRequest $request, AuditRecorder $audit): RedirectResponse
    {
        Gate::authorize('support_bot.manage');

        $organizationId = $this->organizationId($request);
        $actorId = (string) $request->user()->getAuthIdentifier();

        $validated = $request->validate([
            'user_id' => ['required', 'string', 'size:26'],
            'enabled' => ['required', 'boolean'],
        ]);

        /*
         * الحساب يجب أن يكون داخل المؤسسة نفسها. بدون هذا الفحص يمكن لأدمن
         * مؤسسة أن يكتب صفًّا لمستخدم مؤسسة أخرى.
         */
        $belongs = DB::table('users')
            ->where('id', $validated['user_id'])
            ->where('organization_id', $organizationId)
            ->exists();

        abort_unless($belongs, 404);

        $existing = BotAccountAccess::query()
            ->forOrganization($organizationId)
            ->where('user_id', $validated['user_id'])
            ->first();

        $row = BotAccountAccess::query()->updateOrCreate(
            ['organization_id' => $organizationId, 'user_id' => $validated['user_id']],
            [
                'enabled' => (bool) $validated['enabled'],
                'reason' => $request->reason(),
                'updated_by' => $actorId,
            ],
        );

        $audit->record($organizationId, $actorId, 'user',
            'support_bot.account_access_changed', BotAccountAccess::class, (string) $row->getKey(),
            ['enabled' => $existing?->enabled], ['enabled' => $row->enabled], $request->reason());

        return back()->with('success', __('console_bot.flash.access_saved'));
    }

    /** نص محادثة واحدة من الأرشيف. */
    public function conversation(Request $request, string $conversation): JsonResponse
    {
        Gate::authorize('support_bot.archive.view');

        $organizationId = $this->organizationId($request);

        $row = BotConversation::query()
            ->forOrganization($organizationId)
            ->whereKey($conversation)
            ->firstOrFail();

        $messages = BotMessage::query()
            ->where('conversation_id', $row->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(static fn (BotMessage $message): array => [
                'role' => $message->role->value,
                'body' => $message->body,
                'topic' => $message->topic,
                'mode' => $message->mode?->value,
                'generated' => $message->was_generated,
                'failure' => $message->failure_reason,
                'at' => $message->created_at->toIso8601String(),
            ])
            ->values()
            ->all();

        return response()->json(['messages' => $messages]);
    }

    /**
     * @return array{enabled: list<string>, isDefault: bool}
     */
    private function audienceState(string $organizationId, OrganizationSettingQueries $settings): array
    {
        $stored = $settings->value($organizationId, (string) config('support_bot.audiences_setting_key'));

        return [
            'enabled' => is_array($stored)
                ? array_values(array_filter($stored, is_string(...)))
                : array_values((array) config('support_bot.default_audiences', [])),
            'isDefault' => !is_array($stored),
        ];
    }

    /**
     * صفوف المؤسسة والصفوف العامة جنبًا إلى جنب.
     *
     * عرضهما معًا مقصود: التغطية الصامتة للصف العام بصف المؤسسة أربكت المحررين
     * في قوالب الإشعارات — من يحرّر العام لا يرى أثرًا لأن صف المؤسسة هو الفائز.
     *
     * @return list<array<string, mixed>>
     */
    private function entries(string $organizationId): array
    {
        return BotEntry::query()
            ->where(function ($query) use ($organizationId): void {
                $query->where('organization_id', $organizationId)->orWhereNull('organization_id');
            })
            ->orderBy('kind')
            ->orderBy('key')
            ->orderBy('locale')
            ->get()
            ->map(static fn (BotEntry $entry): array => [
                'id' => (string) $entry->getKey(),
                'scope' => $entry->organization_id === null ? 'global' : 'organization',
                'kind' => $entry->kind->value,
                'key' => $entry->key,
                'locale' => $entry->locale,
                'title' => $entry->title,
                'body' => $entry->body,
                'topic' => $entry->topic,
                'audiences' => $entry->audiences,
                'isActive' => $entry->is_active,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rules(string $organizationId): array
    {
        return BotRule::query()
            ->where(function ($query) use ($organizationId): void {
                $query->where('organization_id', $organizationId)->orWhereNull('organization_id');
            })
            ->orderBy('topic')
            ->orderBy('audience')
            ->get()
            ->map(static fn (BotRule $rule): array => [
                'id' => (string) $rule->getKey(),
                'scope' => $rule->organization_id === null ? 'global' : 'organization',
                'topic' => $rule->topic,
                'audience' => $rule->audience,
                'mode' => $rule->mode->value,
                'replyKey' => $rule->reply_key,
                'isActive' => $rule->is_active,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function blockedAccounts(string $organizationId): array
    {
        /*
         * العمود مؤهَّل باسم جدوله: users يحمل organization_id أيضًا، فالاسم
         * المجرّد بعد الـjoin ملتبس وترفضه PostgreSQL.
         */
        return BotAccountAccess::query()
            ->where('support_bot_account_access.organization_id', $organizationId)
            ->join('users', 'users.id', '=', 'support_bot_account_access.user_id')
            ->orderBy('users.name')
            ->get([
                'support_bot_account_access.id',
                'support_bot_account_access.user_id',
                'support_bot_account_access.enabled',
                'support_bot_account_access.reason',
                'users.name',
            ])
            ->map(static fn (object $row): array => [
                'id' => (string) $row->id,
                'userId' => (string) $row->user_id,
                'name' => (string) $row->name,
                'enabled' => (bool) $row->enabled,
                'reason' => (string) $row->reason,
            ])
            ->values()
            ->all();
    }

    /**
     * قائمة المحادثات. لا تحمل نصوصًا — النص يُطلب لمحادثة بعينها عند فتحها،
     * فلا تُحمَّل محادثات مئة مستخدم في صفحة واحدة.
     *
     * @return list<array<string, mixed>>
     */
    private function conversations(string $organizationId): array
    {
        return BotConversation::query()
            ->where('support_bot_conversations.organization_id', $organizationId)
            ->join('users', 'users.id', '=', 'support_bot_conversations.user_id')
            // جلسة بلا رسائل لا تستحق مكانًا في الأرشيف، والفارغ يتصدّر ترتيب
            // PostgreSQL التنازلي فكان يدفن المحادثات الحقيقية.
            ->where('support_bot_conversations.message_count', '>', 0)
            ->orderByRaw('COALESCE(support_bot_conversations.last_message_at, support_bot_conversations.started_at) DESC')
            ->limit(max(1, (int) config('support_bot.archive_per_page', 30)))
            ->get([
                'support_bot_conversations.id',
                'support_bot_conversations.audience',
                'support_bot_conversations.message_count',
                'support_bot_conversations.blocked_count',
                'support_bot_conversations.started_at',
                'support_bot_conversations.last_message_at',
                'support_bot_conversations.closed_at',
                'users.name',
            ])
            ->map(static fn (object $row): array => [
                'id' => (string) $row->id,
                'name' => (string) $row->name,
                'audience' => (string) $row->audience,
                'messages' => (int) $row->message_count,
                'blocked' => (int) $row->blocked_count,
                'startedAt' => (string) $row->started_at,
                'lastMessageAt' => $row->last_message_at === null ? null : (string) $row->last_message_at,
                'closed' => $row->closed_at !== null,
            ])
            ->values()
            ->all();
    }

    private function organizationId(Request $request): string
    {
        $organizationId = (string) data_get($request->user(), 'organization_id');

        abort_if($organizationId === '', 403);

        return $organizationId;
    }
}
