<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\NotificationTemplateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Notifications\Domain\Models\NotificationTemplate;

/**
 * إدارة قوالب الرسائل من الكونسول.
 *
 * القالب العام (organization_id = null) مرجع مشترك بين المؤسسات: يُقرأ ولا
 * يُعدَّل ولا يُحذف من مؤسسة واحدة. التخصيص يكون بإنشاء نسخة للمؤسسة تتفوق
 * عليه عند العرض — وهذا ما تفرضه NotificationTemplatePolicy، لا هذا المتحكّم.
 */
final class NotificationTemplateController extends Controller
{
    public function __construct(
        private readonly AuditRecorder $audit,
    ) {}

    public function store(NotificationTemplateRequest $request): RedirectResponse
    {
        Gate::authorize('create', NotificationTemplate::class);

        $organizationId = $this->organizationId($request);
        $data = $request->validated();
        $body = (string) $data['body'];
        $this->assertNotDuplicated($organizationId, $data, null);
        $this->assertKnownVariables($data);

        $template = NotificationTemplate::query()->create([
            'organization_id' => $organizationId,
            'event_key' => (string) $data['event_key'],
            'channel' => (string) $data['channel'],
            'locale' => (string) $data['locale'],
            'subject' => $this->nullableText($data['subject'] ?? null),
            'body' => $body,
            'provider_template_name' => $this->nullableText($data['provider_template_name'] ?? null),
            'parameters' => $this->parametersIn($body, $this->nullableText($data['subject'] ?? null)),
            'is_active' => (bool) $data['is_active'],
        ]);

        $this->record($request, $template, 'notifications.template_created', null);

        return back()->with('success', __('console_messaging.template_saved'));
    }

    public function update(NotificationTemplateRequest $request, string $template): RedirectResponse
    {
        $record = NotificationTemplate::query()->findOrFail($template);
        Gate::authorize('update', $record);

        $data = $request->validated();
        $body = (string) $data['body'];
        $this->assertNotDuplicated((string) $record->organization_id, $data, (string) $record->getKey());
        $this->assertKnownVariables($data);
        $before = [
            'subject' => $record->subject,
            'body' => $record->body,
            'is_active' => $record->is_active,
        ];

        $record->forceFill([
            'event_key' => (string) $data['event_key'],
            'channel' => (string) $data['channel'],
            'locale' => (string) $data['locale'],
            'subject' => $this->nullableText($data['subject'] ?? null),
            'body' => $body,
            'provider_template_name' => $this->nullableText($data['provider_template_name'] ?? null),
            'parameters' => $this->parametersIn($body, $this->nullableText($data['subject'] ?? null)),
            'is_active' => (bool) $data['is_active'],
        ])->save();

        $this->record($request, $record, 'notifications.template_updated', $before);

        return back()->with('success', __('console_messaging.template_saved'));
    }

    public function destroy(Request $request, string $template): RedirectResponse
    {
        $record = NotificationTemplate::query()->findOrFail($template);
        Gate::authorize('delete', $record);

        $before = [
            'event_key' => $record->event_key,
            'channel' => $record->channel,
            'locale' => $record->locale,
            'is_active' => $record->is_active,
        ];

        $this->record($request, $record, 'notifications.template_deleted', $before);
        $record->delete();

        return back()->with('success', __('console_messaging.template_deleted'));
    }

    /**
     * لا قالبان لنفس (المؤسسة، الحدث، القناة، اللغة).
     *
     * TemplateRenderer يأخذ أول مطابق، فصفّان متطابقان في المفاتيح يجعلان
     * نص الرسالة المُرسَلة غير محدد — تتغير بحسب ترتيب القاعدة لا بحسب قرار
     * الإدارة. القيد يُفرض هنا لأن الجدول يسمح بالتكرار (القوالب العامة
     * والخاصة تتعايش عمدًا على نفس المفاتيح).
     *
     * @param array<string, mixed> $data
     */
    private function assertNotDuplicated(string $organizationId, array $data, ?string $exceptId): void
    {
        $duplicate = NotificationTemplate::query()
            ->forOrganization($organizationId)
            ->where('event_key', (string) $data['event_key'])
            ->where('channel', (string) $data['channel'])
            ->where('locale', (string) $data['locale'])
            ->when($exceptId !== null, static fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'event_key' => (string) __('console_messaging.template_duplicate'),
            ]);
        }
    }

    /**
     * المتغيرات المعلنة = ما يذكره النص فعلًا.
     *
     * TemplateRenderer يرفض الإرسال إن غاب عن الحمولة متغيرٌ معلن، ويترك أي
     * قوس غير معلن كما هو في الرسالة. اشتقاق القائمة من النص يمنع الحالتين.
     *
     * @return list<string>
     */
    private function parametersIn(string $body, ?string $subject): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', $body.' '.(string) $subject, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @param array<string, mixed>|null $before
     */
    private function record(
        Request $request,
        NotificationTemplate $template,
        string $action,
        ?array $before,
    ): void {
        $this->audit->record(
            organizationId: (string) $template->organization_id,
            actorId: (string) $request->user()?->getAuthIdentifier(),
            actorType: 'user',
            action: $action,
            auditableType: 'notification_templates',
            auditableId: (string) $template->getKey(),
            oldValues: $before,
            newValues: [
                'event_key' => $template->event_key,
                'channel' => $template->channel,
                'locale' => $template->locale,
                'is_active' => $template->is_active,
                'parameters' => $template->parameters,
            ],
            reason: (string) __('console_messaging.template_audit_reason'),
        );
    }

    /**
     * نسخة المؤسسة لا تستخدم متغيرًا لا يعرفه القالب العام لنفس الحدث.
     *
     * الحدث يوفّر متغيرات قالبه الأصلي فقط؛ متغير زائد يجعل TemplateRenderer
     * يرفض الإرسال، فتسقط الرسالة عند أول حدث حقيقي بدل أن تُرفض هنا عند الحفظ.
     *
     * @param array<string, mixed> $data
     */
    private function assertKnownVariables(array $data): void
    {
        $original = NotificationTemplate::query()
            ->whereNull('organization_id')
            ->where('event_key', (string) $data['event_key'])
            ->where('channel', (string) $data['channel'])
            ->where('locale', (string) $data['locale'])
            ->first();

        if ($original === null) {
            return;
        }

        $unknown = array_values(array_diff(
            $this->parametersIn((string) $data['body'], $this->nullableText($data['subject'] ?? null)),
            $original->parameters ?? [],
        ));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'body' => (string) __('console_settings.whatsapp_templates.unknown_variables', [
                    'variables' => implode('، ', $unknown),
                ]),
            ]);
        }
    }

    private function nullableText(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function organizationId(Request $request): string
    {
        $organizationId = $request->user()?->getAttribute('organization_id');

        abort_unless(is_string($organizationId) && $organizationId !== '', 403);

        return $organizationId;
    }
}
