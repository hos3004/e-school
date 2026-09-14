<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console\Support;

use App\Http\Controllers\Portal\Support\PortalData;
use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use Modules\Identity\Domain\Models\User;
use Throwable;

/**
 * تركيب نص الرسائل اليدوية التي تُرسل من صفحات الكونسول.
 *
 * كل النصوص الظاهرة من ملفات الترجمة؛ هذا الصنف يرتّب الحقول ويحقن القيم فقط.
 * التواريخ تُعرض بتوقيت المستلم لا بتوقيت المُرسل — الرسالة تُقرأ عند المستلم.
 */
final readonly class ConsoleMessageComposer
{
    /**
     * الحقول التي تقبلها رسالة بيانات الحساب. القائمة مغلقة عمدًا: لا يجوز
     * أن يفتح مُدخَل من المتصفح بابًا لإرسال أي عمود من جدول المستخدمين.
     *
     * @var list<string>
     */
    public const CREDENTIAL_FIELDS = ['name', 'email', 'username', 'password'];

    public function __construct(
        private PortalData $portal,
    ) {}

    /**
     * رسالة بيانات الحساب بالحقول المختارة يدويًا.
     *
     * @param list<string> $fields
     * @return array{subject: string, body: string}
     */
    public function credentials(User $user, array $fields, ?string $temporaryPassword): array
    {
        $lines = [(string) __('console_messaging.credentials.intro')];

        foreach (self::CREDENTIAL_FIELDS as $field) {
            if (!in_array($field, $fields, true)) {
                continue;
            }

            $value = match ($field) {
                'password' => $temporaryPassword,
                default => $user->getAttribute($field),
            };

            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $lines[] = __('console_messaging.credentials.fields.'.$field).': '.trim($value);
        }

        $lines[] = '';
        $lines[] = __('console_messaging.credentials.login_url', ['url' => route('login')]);

        if ($temporaryPassword !== null) {
            $lines[] = (string) __('console_messaging.credentials.password_notice');
        }

        return [
            'subject' => (string) __('console_messaging.credentials.subject'),
            'body' => implode(PHP_EOL, $lines),
        ];
    }

    /**
     * رسالة جدول المواعيد للبرامج المسجَّل بها.
     *
     * @return array{subject: string, body: string}
     */
    public function schedule(
        string $organizationId,
        string $kind,
        string $profileId,
        string $timezone,
        string $locale,
    ): array {
        $sessions = $kind === 'teachers'
            ? $this->portal->teacherScheduleSessions($profileId, $locale, $organizationId)
            : $this->portal->upcomingStudentSessions($profileId, $locale, $organizationId);
        $limit = max(1, (int) config('scheduling.notification_summary.max_sessions', 200));
        $lines = [];

        foreach (array_slice($sessions, 0, $limit) as $session) {
            $subjectName = is_string($session['subject'] ?? null) && $session['subject'] !== ''
                ? $session['subject']
                : (string) ($session['title'] ?? '');
            $lines[] = '• '.$this->formatMoment((string) $session['startsAt'], $timezone, $locale)
                .' — '.$subjectName
                .' — '.(string) data_get($session, 'teacher.name', '');
        }

        if ($lines === []) {
            return [
                'subject' => (string) __('console_messaging.schedule.subject'),
                'body' => (string) __('console_messaging.schedule.empty'),
            ];
        }

        return [
            'subject' => (string) __('console_messaging.schedule.subject'),
            'body' => implode(PHP_EOL, [
                (string) __('console_messaging.schedule.intro'),
                '',
                ...$lines,
                '',
                (string) __('console_messaging.schedule.timezone_notice', ['timezone' => $timezone]),
            ]),
        ];
    }

    /**
     * استبدال المتغيرات الديناميكية في نص حر أو قالب.
     *
     * المتغير غير المعروف يبقى كما هو بدل أن يُفرَّغ بصمت: الإدارة ترى الخطأ
     * في المعاينة قبل الإرسال بدل أن تصل الرسالة ناقصة.
     *
     * @param array<string, string> $variables
     */
    public function render(string $text, array $variables): string
    {
        $replacements = [];

        foreach ($variables as $name => $value) {
            $replacements['{{'.$name.'}}'] = $value;
        }

        return strtr($text, $replacements);
    }

    /**
     * المتغيرات المتاحة لرسالة موجَّهة إلى مستخدم بعينه.
     *
     * @return array<string, string>
     */
    public function variablesForUser(User $user, string $schoolName): array
    {
        return [
            'name' => (string) $user->getAttribute('name'),
            'username' => (string) ($user->getAttribute('username') ?? ''),
            'email' => (string) ($user->getAttribute('email') ?? ''),
            'school' => $schoolName,
            'login_url' => route('login'),
        ];
    }

    private function formatMoment(string $iso, string $timezone, string $locale): string
    {
        $format = (string) config('notifications.localization.datetime_format', 'Y-m-d H:i T');

        try {
            return CarbonImmutable::parse($iso, 'UTC')
                ->setTimezone(new CarbonTimeZone($timezone))
                ->locale($locale)
                ->translatedFormat($format);
        } catch (Throwable) {
            return $iso;
        }
    }
}
