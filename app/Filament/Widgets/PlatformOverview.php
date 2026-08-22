<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * البطاقات العلوية في لوحة التحكم.
 *
 * الأرقام تُقرأ بالاستعلام المباشر على أسماء الجداول لا عبر نماذج الموديولات،
 * لأن هذه الويدجت تعيش خارج أي موديول ولا يجوز لها استيراد نماذجها.
 */
final class PlatformOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $today = CarbonImmutable::now('UTC')->startOfDay();
        $monthStart = CarbonImmutable::now('UTC')->startOfMonth();

        return [
            $this->students(),
            $this->sessionsToday($today),
            $this->attendanceRate($monthStart),
            $this->payrollThisMonth($monthStart),
        ];
    }

    private function students(): Stat
    {
        $total = DB::table('student_profiles')->whereNull('deleted_at')->count();

        $active = DB::table('enrollments')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->distinct('student_profile_id')
            ->count('student_profile_id');

        $frozen = DB::table('enrollments')->where('status', 'frozen')->count();

        return Stat::make('الطلاب', (string) $total)
            ->description($active.' قيد نشط · '.$frozen.' مجمَّد')
            ->descriptionIcon('heroicon-m-users')
            ->color($frozen > 0 ? 'warning' : 'success');
    }

    private function sessionsToday(CarbonImmutable $today): Stat
    {
        $base = DB::table('sessions')
            ->whereNull('deleted_at')
            ->whereBetween('scheduled_start', [$today, $today->addDay()]);

        $total = (clone $base)->count();
        $done = (clone $base)->where('status', 'completed')->count();
        $upcoming = (clone $base)->whereIn('status', ['scheduled', 'confirmed'])->count();

        return Stat::make('حصص اليوم', (string) $total)
            ->description($done.' مكتملة · '.$upcoming.' قادمة')
            ->descriptionIcon('heroicon-m-calendar-days')
            ->color('info');
    }

    /**
     * نسبة الحضور = (حاضر + متأخر + جزئي) ÷ كل سجلات الحضور المعتمدة هذا الشهر.
     */
    private function attendanceRate(CarbonImmutable $monthStart): Stat
    {
        $rows = DB::table('attendances')
            ->join('session_participants', 'session_participants.id', '=', 'attendances.session_participant_id')
            ->join('sessions', 'sessions.id', '=', 'session_participants.session_id')
            ->where('sessions.scheduled_start', '>=', $monthStart)
            ->selectRaw('attendances.status, count(*) as total')
            ->groupBy('attendances.status')
            ->pluck('total', 'status');

        $all = (int) $rows->sum();
        $present = (int) ($rows['present'] ?? 0) + (int) ($rows['late'] ?? 0) + (int) ($rows['partial'] ?? 0);
        $rate = $all > 0 ? (int) round(($present / $all) * 100) : 0;

        $absent = (int) ($rows['absent'] ?? 0) + (int) ($rows['no_show'] ?? 0);

        return Stat::make('نسبة الحضور هذا الشهر', $rate.'%')
            ->description($all > 0 ? $absent.' غياب من '.$all.' سجل' : 'لا سجلات بعد')
            ->descriptionIcon('heroicon-m-check-badge')
            ->color(match (true) {
                $all === 0 => 'gray',
                $rate >= 85 => 'success',
                $rate >= 70 => 'warning',
                default => 'danger',
            });
    }

    /**
     * المبالغ مخزَّنة بالوحدة الصغرى (القروش) — تُعرض مقسومة على 100.
     */
    private function payrollThisMonth(CarbonImmutable $monthStart): Stat
    {
        $net = (int) DB::table('payroll_entries')
            ->where('created_at', '>=', $monthStart)
            ->sum('amount');

        $deferred = DB::table('payroll_entries')
            ->where('created_at', '>=', $monthStart)
            ->where('status', 'deferred')
            ->count();

        return Stat::make(
            'مستحقات الشهر',
            number_format($net / 100, 2).' ج.م',
        )
            ->description($deferred > 0 ? $deferred.' قيدة مؤجَّلة' : 'لا قيود مؤجَّلة')
            ->descriptionIcon('heroicon-m-banknotes')
            ->color($net >= 0 ? 'success' : 'danger');
    }
}
