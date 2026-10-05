<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Enums;

use Carbon\CarbonImmutable;
use Modules\Notifications\Domain\Models\PopupCampaignUserState;

enum PopupFrequency: string
{
    case Once = 'once';
    case OncePerLogin = 'once_per_login';
    case OncePerDay = 'once_per_day';
    case UntilAcknowledged = 'until_acknowledged';
    case EveryEligibleVisit = 'every_eligible_visit';

    public function label(): string
    {
        return __('notifications::popups.frequency.'.$this->value);
    }

    /**
     * هل يسمح التكرار بظهور الحملة الآن؟ القرار Server-Side بالكامل.
     *
     * @param PopupCampaignUserState|null $state حالة المستخدم المجمّعة (null = لم يشاهد أبدًا)
     * @param string|null $loginMarker علامة جلسة الدخول الحالية من الجلسة الآمنة
     */
    public function allowsShow(?PopupCampaignUserState $state, ?string $loginMarker, CarbonImmutable $now): bool
    {
        if ($state === null) {
            return true;
        }

        // الإقرار يوقف كل شيء دائمًا.
        if ($state->acknowledged_at !== null) {
            return false;
        }

        return match ($this) {
            // مرة واحدة: أول Impression ناجح يقفلها.
            self::Once => $state->first_seen_at === null,

            // مرة بعد كل تسجيل دخول: مقارنة بعلامة الجلسة المخزنة وقت آخر مشاهدة.
            self::OncePerLogin => $state->login_marker === null || $state->login_marker !== $loginMarker,

            // مرة يوميًا: بتوقيت UTC.
            self::OncePerDay => $state->last_seen_at === null
                || $state->last_seen_at->toDateString() !== $now->toDateString(),

            // تستمر حتى الإقرار (فوق) — الإغلاق المتعمد يسددها في طبقة الأهلية العامة.
            self::UntilAcknowledged => true,

            // للاستخدام المحدود — مسموحة لكن لا تُوصى افتراضيًا.
            self::EveryEligibleVisit => true,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(static fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
