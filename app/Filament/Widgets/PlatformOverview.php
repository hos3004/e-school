<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopesToOrganization;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * البطاقات العلوية في لوحة التحكم.
 *
 * الأرقام تُقرأ بالاستعلام المباشر على أسماء الجداول لا عبر نماذج الموديولات،
 * لأن هذه الويدجت تعيش خارج أي موديول ولا يجوز لها استيراد نماذجها.
 */
final class PlatformOverview extends StatsOverviewWidget
{
    use ScopesToOrganization;

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
            $this->teachers(),
            $this->activePrograms(),
            $this->sessionsToday($today),
            $this->attendanceRate($monthStart),
            ...((bool) config('features.payroll') ? [$this->payrollThisMonth($monthStart)] : []),
        ];
    }

    private function students(): Stat
    {
        $total = $this->scoped('student_profiles')->whereNull('deleted_at')->count();

        $active = $this->scoped('enrollments')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->distinct('student_profile_id')
            ->count('student_profile_id');

        $frozen = $this->scoped('enrollments')
            ->whereNull('deleted_at')
            ->where('status', 'frozen')
            ->count();

        return Stat::make(__('dashboard.stats.students.label'), (string) $total)
            ->description(__('dashboard.stats.students.description', [
                'active' => $active,
                'frozen' => $frozen,
            ]))
            ->descriptionIcon('heroicon-m-users')
            ->color($frozen > 0 ? 'warning' : 'success')
            ->url('/admin/students');
    }

    private function teachers(): Stat
    {
        $total = $this->scoped('staff_profiles')->whereNull('deleted_at')->count();

        return Stat::make(__('dashboard.stats.teachers.label'), (string) $total)
            ->description(__('dashboard.stats.teachers.description'))
            ->descriptionIcon('heroicon-m-user-group')
            ->color('info')
            ->url('/admin/staff-profiles');
    }

    private function activePrograms(): Stat
    {
        $total = $this->scoped('programs')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->count();

        return Stat::make(__('dashboard.stats.programs.label'), (string) $total)
            ->description(__('dashboard.stats.programs.description'))
            ->descriptionIcon('heroicon-m-academic-cap')
            ->color('success')
            ->url('/admin/program-filaments');
    }

    private function sessionsToday(CarbonImmutable $today): Stat
    {
        $base = $this->scoped('sessions')
            ->whereNull('deleted_at')
            ->whereBetween('scheduled_start', [$today, $today->addDay()]);

        $total = (clone $base)->count();
        $done = (clone $base)->where('status', 'completed')->count();
        $upcoming = (clone $base)->whereIn('status', ['scheduled', 'confirmed'])->count();

        return Stat::make(__('dashboard.stats.sessions_today.label'), (string) $total)
            ->description(__('dashboard.stats.sessions_today.description', [
                'done' => $done,
                'upcoming' => $upcoming,
            ]))
            ->descriptionIcon('heroicon-m-calendar-days')
            ->color('info')
            ->url('/admin/sessions');
    }

    /**
     * نسبة الحضور = (حاضر + متأخر + جزئي) ÷ كل سجلات الحضور المعتمدة هذا الشهر.
     */
    private function attendanceRate(CarbonImmutable $monthStart): Stat
    {
        $rows = $this->scopedVia('session_participants', 'sessions', 'session_id')
            ->join('attendances', 'attendances.session_participant_id', '=', 'session_participants.id')
            ->whereNull('sessions.deleted_at')
            ->where('sessions.scheduled_start', '>=', $monthStart)
            ->selectRaw('attendances.status, count(*) as total')
            ->groupBy('attendances.status')
            ->pluck('total', 'status');

        $all = (int) $rows->sum();
        $present = (int) ($rows['present'] ?? 0) + (int) ($rows['late'] ?? 0) + (int) ($rows['partial'] ?? 0);
        $rate = $all > 0 ? (int) round(($present / $all) * 100) : 0;

        $absent = (int) ($rows['absent'] ?? 0) + (int) ($rows['no_show'] ?? 0);

        return Stat::make(
            __('dashboard.stats.attendance_rate.label'),
            $rate.'%',
        )
            ->description($all > 0
                ? __('dashboard.stats.attendance_rate.description', ['absent' => $absent, 'total' => $all])
                : __('dashboard.stats.attendance_rate.empty_description'))
            ->descriptionIcon('heroicon-m-check-badge')
            ->color(match (true) {
                $all === 0 => 'gray',
                $rate >= 85 => 'success',
                $rate >= 70 => 'warning',
                default => 'danger',
            })
            ->url('/admin/attendance-filaments');
    }

    /**
     * المبالغ مخزَّنة بالوحدة الصغرى (القروش) — تُعرض مقسومة على 100.
     */
    private function payrollThisMonth(CarbonImmutable $monthStart): Stat
    {
        $net = (int) $this->scoped('payroll_entries')
            ->where('created_at', '>=', $monthStart)
            ->sum('amount');

        $deferred = $this->scoped('payroll_entries')
            ->where('created_at', '>=', $monthStart)
            ->where('status', 'deferred')
            ->count();

        return Stat::make(
            __('dashboard.stats.payroll.label'),
            number_format($net / 100, 2).' '.__('dashboard.stats.payroll.currency'),
        )
            ->description($deferred > 0
                ? __('dashboard.stats.payroll.deferred_description', ['count' => $deferred])
                : __('dashboard.stats.payroll.no_deferred_description'))
            ->descriptionIcon('heroicon-m-banknotes')
            ->color($net >= 0 ? 'success' : 'danger')
            ->url('/admin/payroll-entries');
    }
}
