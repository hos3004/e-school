<?php

declare(strict_types=1);

namespace App\Support\SupportBot;

use App\Http\Controllers\Learning\TeachingStudents;
use App\Http\Controllers\Portal\Support\PortalData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Modules\SupportBot\Domain\Contracts\SupportBotDataSource;
use Modules\SupportBot\Domain\Enums\BotTopic;
use Throwable;

/**
 * قراءة بيانات المنصة للبوت — التنفيذ الحقيقي للعقد.
 *
 * يعيش في app/ لا في الموديول لسببين: أنه يركّب قراءات من موديولات عدة، وأن
 * موديولًا لا يجوز أن يعتمد على app/. نفس موضع مُحدِّد مستلمي الإشعارات.
 *
 * ثلاثة التزامات تحكم كل سطر هنا:
 *
 *  1. **صلاحيات المستخدم نفسه.** كل قراءة خلف `$user->can(...)`، فما لا يراه من
 *     الموقع لا يصل البوت. وهذا ما يجعل مشرف الجودة يحصل على ما يخصّه بالضبط:
 *     هو يملك صلاحية القراءة فعلًا، وإنما يجهل موضع الشاشة.
 *
 *  2. **المستخدم الموثَّق وحده.** المعرّف الممرَّر يجب أن يطابق صاحب الجلسة،
 *     وإلا فلا بيانات. حارس ضد أي مسار مستقبلي يمرّر معرّفًا من طرف العميل.
 *
 *  3. **لا أسماء أشخاص ولا وسائل تواصل ولا مبالغ.** تخرج التواريخ والأعداد
 *     والمواد فقط. اسم زميل أو معلم ليس ضروريًا للإجابة، وإخراجه يعني إرسال
 *     بيانات شخص إلى مزوّد خارجي بلا مقابل حقيقي — ولذلك نكتفي بالعدد ونحيل
 *     إلى الشاشة لمن أراد الأسماء.
 */
final readonly class PlatformDataSource implements SupportBotDataSource
{
    /** أقصى عدد حصص تُذكر في السياق — ما بعدها يُحال إلى الشاشة. */
    private const int MAX_SESSIONS = 3;

    public function __construct(
        private AuthFactory $auth,
        private PortalData $portal,
        private TeachingStudents $teaching,
    ) {}

    /**
     * @return list<array{label: string, value: string}>
     */
    public function factsFor(string $organizationId, string $userId, BotTopic $topic, string $locale): array
    {
        $user = $this->actor($organizationId, $userId);

        if ($user === null) {
            return [];
        }

        /*
         * الاستعلامات هنا تخدم محادثة، لا شاشة. تعثّر أحدها يجب أن ينتج إجابة
         * من المعرفة العامة لا خطأ في وجه المستخدم — فالبوت بلا بيانات أنفع من
         * بوت لا يرد.
         */
        try {
            return match ($topic) {
                BotTopic::Schedule, BotTopic::SessionJoin => $this->scheduleFacts($user, $organizationId, $userId, $locale),
                BotTopic::Attendance => $this->attendanceFacts($user, $organizationId, $userId),
                BotTopic::MyStudents => $this->teachingFacts($user, $organizationId, $userId, $locale),
                default => [],
            };
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * صاحب الجلسة إن طابق المعرّف والمؤسسة معًا.
     */
    private function actor(string $organizationId, string $userId): (Authenticatable&Authorizable)|null
    {
        $user = $this->auth->guard()->user();

        if (!$user instanceof Authenticatable || !$user instanceof Authorizable) {
            return null;
        }

        if ((string) $user->getAuthIdentifier() !== $userId) {
            return null;
        }

        return (string) data_get($user, 'organization_id') === $organizationId ? $user : null;
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function scheduleFacts(Authorizable $user, string $organizationId, string $userId, string $locale): array
    {
        if (!$user->can('schedule.view')) {
            return [];
        }

        $studentProfileId = $this->portal->studentProfileId($userId, $organizationId);

        if ($studentProfileId === null) {
            return [];
        }

        $sessions = $this->portal->upcomingStudentSessions($studentProfileId, $locale, $organizationId);

        if ($sessions === []) {
            return [[
                'label' => (string) __('supportbot::facts.upcoming_sessions'),
                'value' => (string) __('supportbot::facts.no_upcoming_sessions'),
            ]];
        }

        $facts = [];

        foreach (array_slice($sessions, 0, self::MAX_SESSIONS) as $index => $session) {
            $facts[] = [
                'label' => (string) __('supportbot::facts.session_number', ['number' => $index + 1]),
                'value' => $this->describeSession($session, $locale),
            ];
        }

        if (count($sessions) > self::MAX_SESSIONS) {
            $facts[] = [
                'label' => (string) __('supportbot::facts.total_upcoming'),
                'value' => (string) count($sessions),
            ];
        }

        return $facts;
    }

    /**
     * @param array<string, mixed> $session
     */
    private function describeSession(array $session, string $locale): string
    {
        $timezone = is_string($session['timezone'] ?? null) && $session['timezone'] !== ''
            ? $session['timezone']
            : (string) config('app.timezone');

        $startsAt = CarbonImmutable::parse((string) $session['startsAt'])->setTimezone($timezone);

        $subject = is_string($session['subject'] ?? null) ? trim($session['subject']) : '';
        $when = $startsAt->locale($locale)->isoFormat('dddd D MMMM، h:mm a');

        return $subject === '' ? $when : $when.' — '.$subject;
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function attendanceFacts(Authorizable $user, string $organizationId, string $userId): array
    {
        if (!$user->can('attendance.view')) {
            return [];
        }

        $studentProfileId = $this->portal->studentProfileId($userId, $organizationId);

        if ($studentProfileId === null) {
            return [];
        }

        $rate = $this->portal->attendanceRate($studentProfileId, $organizationId);

        if ($rate === null) {
            return [];
        }

        return [[
            'label' => (string) __('supportbot::facts.attendance_rate'),
            'value' => round($rate).'%',
        ]];
    }

    /**
     * عدد الطلاب ومساراتهم — بلا أسماء. من أراد الأسماء ففي شاشة «طلابي».
     *
     * @return list<array{label: string, value: string}>
     */
    private function teachingFacts(Authorizable $user, string $organizationId, string $userId, string $locale): array
    {
        if (!$user->can('student.view')) {
            return [];
        }

        $staffProfileId = $this->portal->staffProfileId($userId, $organizationId);

        if ($staffProfileId === null) {
            return [];
        }

        $roster = $this->teaching->roster($organizationId, $staffProfileId, $locale);

        $tracks = [];

        foreach ($roster as $student) {
            foreach ($student['tracks'] ?? [] as $track) {
                $name = is_string($track['name'] ?? null) ? trim($track['name']) : '';

                if ($name !== '') {
                    $tracks[$name] = true;
                }
            }
        }

        $facts = [[
            'label' => (string) __('supportbot::facts.active_students'),
            'value' => (string) count($roster),
        ]];

        if ($tracks !== []) {
            $facts[] = [
                'label' => (string) __('supportbot::facts.tracks'),
                'value' => implode('، ', array_slice(array_keys($tracks), 0, 8)),
            ];
        }

        return $facts;
    }
}
