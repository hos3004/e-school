<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Console\Support\ConsoleMessageComposer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\SendAudienceMessageRequest;
use App\Http\Requests\Console\SendPersonMessageRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Identity\Application\Actions\IssueTemporaryPassword;
use Modules\Identity\Domain\Models\User;
use Modules\Notifications\Application\Actions\QueueManualNotificationAction;
use Modules\Notifications\Application\Services\ManualNotificationRecipientResolver;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\ManualAudience;
use Modules\Notifications\Domain\Enums\ManualRecipientType;
use Modules\Notifications\Domain\Models\NotificationTemplate;
use Modules\Notifications\Domain\ValueObjects\ManualNotificationDispatchResult;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\BusinessRuleViolation;
use Throwable;

/**
 * المراسلة اليدوية من الكونسول: من الملف الشخصي ومن صفحة الفصل.
 *
 * كل مسار هنا يمر بنفس محرّك الإشعارات: يُكتب في صندوق الصادر أولًا ثم تُسلّم
 * البوابة. لا استدعاء مباشر لمزوّد واتساب من الكونسول — وإلا فُقد التتبع
 * وإعادة المحاولة والتدقيق وجدولة الإرسال.
 */
final class MessagingController extends Controller
{
    public function __construct(
        private readonly ConsoleMessageComposer $composer,
        private readonly ManualNotificationRecipientResolver $recipients,
        private readonly QueueManualNotificationAction $queue,
        private readonly ConsoleContext $context,
    ) {}

    /**
     * أهداف المراسلة الجماعية القابلة للاختيار، والقوالب الجاهزة.
     */
    public function targets(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('notifications.outbox.create'), 403);

        $input = $request->validate([
            'type' => ['required', Rule::in(array_keys(ManualRecipientType::options()))],
            'search' => ['nullable', 'string', 'max:120'],
        ]);
        $organizationId = $this->organizationId($request);
        $type = ManualRecipientType::from((string) $input['type']);

        return response()->json([
            'targets' => collect($this->recipients->search(
                $organizationId,
                $type,
                (string) ($input['search'] ?? ''),
            ))->map(static fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
            ])->values()->all(),
        ]);
    }

    /**
     * القوالب المتاحة للإدارة عند تأليف رسالة يدوية.
     */
    public function templates(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('notifications.outbox.create'), 403);

        $organizationId = $this->organizationId($request);

        return response()->json([
            'templates' => NotificationTemplate::query()
                ->active()
                ->visibleToOrganization($organizationId)
                ->orderBy('event_key')
                ->get(['id', 'event_key', 'channel', 'locale', 'subject', 'body', 'parameters'])
                ->map(static fn (NotificationTemplate $template): array => [
                    'id' => $template->id,
                    'event_key' => $template->event_key,
                    'channel' => $template->channel,
                    'locale' => $template->locale,
                    'subject' => $template->subject,
                    'body' => $template->body,
                    'parameters' => $template->parameters,
                ])
                ->all(),
        ]);
    }

    /**
     * رسالة إلى صاحب ملف واحد: بيانات حساب، أو جدول مواعيد، أو نص حر.
     */
    public function person(
        SendPersonMessageRequest $request,
        string $kind,
        string $profile,
        IssueTemporaryPassword $issuePassword,
    ): RedirectResponse {
        $organizationId = $this->organizationId($request);
        $actorId = (string) $request->user()?->getAuthIdentifier();
        $data = $request->validated();
        [$profileId, $user] = $this->personTarget($organizationId, $kind, $profile);
        $recipientType = $kind === 'teachers'
            ? ManualRecipientType::Teacher
            : ManualRecipientType::Student;
        $channel = Channel::from((string) $data['channel']);

        try {
            /*
             * الحراس قبل تركيب المحتوى، لا بعده: تركيب رسالة بيانات الحساب قد
             * يُصدر كلمة مرور مؤقتة تبطل الحالية. لو فشل التقييد بعد ذلك لبقي
             * صاحب الحساب بلا كلمة مرور صالحة ولا رسالة تحمل الجديدة.
             */
            if (!$this->queue->isDispatchable(
                organizationId: $organizationId,
                recipientType: $recipientType,
                targetId: (string) $user->getKey(),
                channel: $channel,
                requestId: (string) $data['request_id'],
            )) {
                return back()->with('success', (string) __('console_messaging.already_sent'));
            }

            $content = $this->personContent(
                $data,
                $kind,
                $organizationId,
                $profileId,
                $user,
                $actorId,
                $issuePassword,
            );

            $result = $this->queue->execute(
                organizationId: $organizationId,
                actorId: $actorId,
                recipientType: $recipientType,
                targetId: (string) $user->getKey(),
                channel: $channel,
                subject: $content['subject'],
                body: $content['body'],
                reason: (string) $data['reason'],
                requestId: (string) $data['request_id'],
                locale: $this->localeFor($user),
                scheduledFor: $this->scheduledFor($request, $data),
            );
        } catch (BusinessRuleViolation $error) {
            return $this->failure($error);
        }

        return back()->with('success', $this->summary($result, $data));
    }

    /**
     * رسالة إلى أطراف فصل: مجموعة أو كورس أو جدول، بجمهور محدد.
     */
    public function audience(SendAudienceMessageRequest $request): RedirectResponse
    {
        $organizationId = $this->organizationId($request);
        $actorId = (string) $request->user()?->getAuthIdentifier();
        $data = $request->validated();
        $context = $this->context->forRequest($request);
        $variables = [
            'school' => (string) ($context['school']['name'] ?? ''),
            'login_url' => route('login'),
        ];

        try {
            $result = $this->queue->execute(
                organizationId: $organizationId,
                actorId: $actorId,
                recipientType: ManualRecipientType::from((string) $data['recipient_type']),
                targetId: (string) $data['target_id'],
                channel: Channel::from((string) $data['channel']),
                subject: $this->composer->render((string) $data['subject'], $variables),
                body: $this->composer->render((string) $data['body'], $variables),
                reason: (string) $data['reason'],
                requestId: (string) $data['request_id'],
                locale: (string) config('notifications.locale.fallback', 'ar'),
                audience: ManualAudience::from((string) $data['audience']),
                scheduledFor: $this->scheduledFor($request, $data),
            );
        } catch (BusinessRuleViolation $error) {
            return $this->failure($error);
        }

        return back()->with('success', $this->summary($result, $data));
    }

    /**
     * الخطأ يعود بصفته خطأ نموذج لا رسالة flash.
     *
     * Inertia يعتبر الاستجابة الحاملة لـerrors فشلًا فينادي onError، فتبقى
     * نافذة التأليف مفتوحة بما كتبه المرسِل. رسالة flash كانت تُقرأ نجاحًا
     * فتُغلق النافذة ويضيع النص.
     */
    private function failure(BusinessRuleViolation $error): RedirectResponse
    {
        return back()->withErrors(['message' => $error->getMessage()]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{subject: string, body: string}
     */
    private function personContent(
        array $data,
        string $kind,
        string $organizationId,
        string $profileId,
        User $user,
        string $actorId,
        IssueTemporaryPassword $issuePassword,
    ): array {
        $locale = $this->localeFor($user);
        $timezone = $this->timezoneFor($user);

        return match ((string) $data['kind']) {
            'credentials' => $this->composer->credentials(
                $user,
                array_values((array) ($data['fields'] ?? [])),
                // كلمة المرور تُولَّد فقط إذا اختارها المرسل صراحةً، لأن
                // توليدها يبطل كلمة المرور الحالية لصاحب الحساب فورًا.
                in_array('password', (array) ($data['fields'] ?? []), true)
                    ? $issuePassword->execute($user, $actorId, (string) $data['reason'])
                    : null,
            ),
            'schedule' => $this->composer->schedule(
                $organizationId,
                $kind,
                $profileId,
                $timezone,
                $locale,
            ),
            default => [
                'subject' => $this->composer->render(
                    (string) $data['subject'],
                    $this->composer->variablesForUser($user, ''),
                ),
                'body' => $this->composer->render(
                    (string) $data['body'],
                    $this->composer->variablesForUser($user, ''),
                ),
            ],
        };
    }

    /**
     * @return array{0: string, 1: User}
     */
    private function personTarget(string $organizationId, string $kind, string $profile): array
    {
        if ($kind === 'teachers') {
            $record = StaffProfile::query()
                ->where('organization_id', $organizationId)
                ->whereKey($profile)
                ->firstOrFail();
        } else {
            $record = StudentProfile::query()
                ->where('organization_id', $organizationId)
                ->whereKey($profile)
                ->firstOrFail();
        }

        $user = User::query()
            ->where('organization_id', $organizationId)
            ->whereKey((string) $record->user_id)
            ->firstOrFail();

        return [(string) $record->getKey(), $user];
    }

    /**
     * موعد الإرسال يُقرأ بتوقيت المُرسِل ثم يُخزَّن UTC.
     *
     * حقل datetime-local لا يحمل منطقة زمنية، فتفسيره بتوقيت التطبيق يزيح
     * الموعد بفارق منطقة الإدارة: إدارة بتوقيت القاهرة تختار السادسة مساءً
     * فتخرج الرسالة التاسعة. التفسير هنا بتوقيت المستخدم المنفّذ.
     *
     * @param array<string, mixed> $data
     */
    private function scheduledFor(Request $request, array $data): ?CarbonImmutable
    {
        $value = $data['scheduled_for'] ?? null;

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $timezone = $request->user() instanceof User
            ? $this->timezoneFor($request->user())
            : 'UTC';

        try {
            $moment = CarbonImmutable::parse($value, $timezone)->utc();
        } catch (Throwable) {
            throw BusinessRuleViolation::make(
                'notifications.manual_schedule_invalid',
                'notifications::errors.manual_request_invalid',
            );
        }

        if ($moment->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
            throw BusinessRuleViolation::make(
                'notifications.manual_schedule_past',
                'notifications::errors.manual_schedule_past',
            );
        }

        return $moment;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function summary(ManualNotificationDispatchResult $result, array $data): string
    {
        // طلب سبق تنفيذه لا يُعلن نجاحًا جديدًا: الأرقام المعادة أرقام القديم.
        if ($result->alreadyProcessed) {
            return (string) __('console_messaging.already_sent');
        }

        $key = ($data['scheduled_for'] ?? null) === null
            ? 'console_messaging.queued'
            : 'console_messaging.scheduled';

        return (string) __($key, [
            'count' => $result->queuedCount,
            'recipients' => $result->recipientCount,
        ]);
    }

    private function localeFor(User $user): string
    {
        $locale = $user->getAttribute('locale');

        return is_string($locale) && $locale !== ''
            ? $locale
            : (string) config('notifications.locale.fallback', 'ar');
    }

    private function timezoneFor(User $user): string
    {
        $timezone = $user->getAttribute('timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
    }

    private function organizationId(Request $request): string
    {
        $organizationId = $request->user()?->getAttribute('organization_id');

        abort_unless(is_string($organizationId) && $organizationId !== '', 403);

        return $organizationId;
    }
}
