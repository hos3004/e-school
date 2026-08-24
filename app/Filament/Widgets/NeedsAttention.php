<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopesToOrganization;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;

/**
 * «يحتاج انتباهك» — الصف الذي يجيب سؤال المشرف الأول كل صباح:
 * ما الذي تعطّل أو ينتظرني الآن؟
 *
 * كل بند هنا فعل مطلوب، لا معلومة عامة، وكل بند رابط يفتح صفحة الإجراء.
 */
final class NeedsAttention extends Widget
{
    use ScopesToOrganization;

    protected string $view = 'filament.widgets.needs-attention';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $now = CarbonImmutable::now('UTC');

        return [
            'title' => __('dashboard.needs_attention.title'),
            'subtitle' => __('dashboard.needs_attention.subtitle'),
            'empty' => __('dashboard.needs_attention.empty'),
            'items' => array_values(array_filter([
                $this->item(
                    'postponements_pending',
                    $this->scopedVia('postponement_requests', 'sessions', 'session_id')
                        ->whereIn('postponement_requests.status', ['requested', 'alternative_proposed'])
                        ->whereNull('sessions.deleted_at')
                        ->count(),
                    '/admin/postponement-requests',
                    'heroicon-o-clock',
                    'warning',
                ),
                $this->item(
                    'postponements_expired',
                    $this->scopedVia('postponement_requests', 'sessions', 'session_id')
                        ->whereIn('postponement_requests.status', ['requested', 'alternative_proposed'])
                        ->where('postponement_requests.expires_at', '<', $now)
                        ->whereNull('sessions.deleted_at')
                        ->count(),
                    '/admin/postponement-requests',
                    'heroicon-o-exclamation-triangle',
                    'danger',
                ),
                $this->item(
                    'sessions_awaiting_review',
                    $this->scoped('sessions')
                        ->whereNull('deleted_at')
                        ->where('status', 'awaiting_review')
                        ->count(),
                    '/admin/sessions',
                    'heroicon-o-clipboard-document-check',
                    'warning',
                ),
                $this->item(
                    'registrations_submitted',
                    $this->scoped('registration_applications')
                        ->whereNull('deleted_at')
                        ->where('status', 'submitted')
                        ->count(),
                    '/admin/registration-applications',
                    'heroicon-o-inbox-arrow-down',
                    'info',
                ),
                $this->item(
                    'enrollments_frozen',
                    $this->scoped('enrollments')
                        ->whereNull('deleted_at')
                        ->where('status', 'frozen')
                        ->count(),
                    '/admin/students',
                    'heroicon-o-lock-closed',
                    'danger',
                ),
                $this->item(
                    'reactivations_pending',
                    $this->scoped('reactivation_requests')->where('status', 'pending')->count(),
                    '/admin/discipline-reactivations',
                    'heroicon-o-arrow-path',
                    'info',
                ),
                $this->item(
                    'availability_unapproved',
                    $this->scopedVia('teacher_availability', 'staff_profiles', 'staff_profile_id')
                        ->where('teacher_availability.approval_status', 'pending')
                        ->whereNull('staff_profiles.deleted_at')
                        ->count(),
                    '/admin/staff-profiles',
                    'heroicon-o-calendar-days',
                    'warning',
                ),
                ...((bool) config('features.payroll') ? [
                    $this->item(
                        'payroll_adjustments_pending',
                        $this->scoped('payroll_adjustments')
                            ->whereNull('approved_at')
                            ->whereNull('rejected_at')
                            ->count(),
                        '/admin/payroll-entries',
                        'heroicon-o-banknotes',
                        'warning',
                    ),
                ] : []),
                $this->item(
                    'notifications_failed',
                    $this->scoped('notification_outbox')->where('status', 'failed')->count(),
                    '/admin/notification-outboxes',
                    'heroicon-o-bell-alert',
                    'danger',
                ),
            ])),
        ];
    }

    /**
     * البند الصفري لا يُعرض — القائمة تبقى قصيرة وقابلة للتصرف.
     *
     * @return array{key: string, label: string, count: int, href: string, icon: string, color: string}|null
     */
    private function item(string $key, int $count, string $href, string $icon, string $color): ?array
    {
        if ($count === 0) {
            return null;
        }

        return [
            'key' => $key,
            'label' => __('dashboard.needs_attention.items.'.$key),
            'count' => $count,
            'href' => $href,
            'icon' => $icon,
            'color' => $color,
        ];
    }
}
