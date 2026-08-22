<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * «يحتاج انتباهك» — الصف الذي يجيب سؤال المشرف الأول كل صباح:
 * ما الذي تعطّل أو ينتظرني الآن؟
 *
 * كل بند هنا فعل مطلوب، لا معلومة عامة.
 */
final class NeedsAttention extends Widget
{
    protected string $view = 'filament.widgets.needs-attention';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $now = CarbonImmutable::now('UTC');

        return [
            'items' => array_values(array_filter([
                $this->item(
                    'طلبات تأجيل تنتظر ردًا',
                    DB::table('postponement_requests')
                        ->whereIn('status', ['requested', 'alternative_proposed'])
                        ->count(),
                    'heroicon-o-clock',
                    'warning',
                ),
                $this->item(
                    'طلبات تأجيل انقضت مهلتها',
                    DB::table('postponement_requests')
                        ->whereIn('status', ['requested', 'alternative_proposed'])
                        ->where('expires_at', '<', $now)
                        ->count(),
                    'heroicon-o-exclamation-triangle',
                    'danger',
                ),
                $this->item(
                    'حصص تنتظر اعتماد الحضور',
                    DB::table('sessions')
                        ->whereNull('deleted_at')
                        ->where('status', 'awaiting_review')
                        ->count(),
                    'heroicon-o-clipboard-document-check',
                    'warning',
                ),
                $this->item(
                    'قيود مجمَّدة تأديبيًا',
                    DB::table('enrollments')->where('status', 'frozen')->count(),
                    'heroicon-o-lock-closed',
                    'danger',
                ),
                $this->item(
                    'طلبات فك تجميد معلّقة',
                    DB::table('reactivation_requests')->where('status', 'pending')->count(),
                    'heroicon-o-arrow-path',
                    'info',
                ),
                $this->item(
                    'تسويات مالية تنتظر الاعتماد',
                    DB::table('payroll_adjustments')
                        ->whereNull('approved_at')
                        ->whereNull('rejected_at')
                        ->count(),
                    'heroicon-o-banknotes',
                    'warning',
                ),
                $this->item(
                    'إشعارات فشل إرسالها',
                    DB::table('notification_outbox')->where('status', 'failed')->count(),
                    'heroicon-o-bell-alert',
                    'danger',
                ),
            ])),
        ];
    }

    /**
     * البند الصفري لا يُعرض — القائمة تبقى قصيرة وقابلة للتصرف.
     *
     * @return array{label: string, count: int, icon: string, color: string}|null
     */
    private function item(string $label, int $count, string $icon, string $color): ?array
    {
        if ($count === 0) {
            return null;
        }

        return ['label' => $label, 'count' => $count, 'icon' => $icon, 'color' => $color];
    }
}
