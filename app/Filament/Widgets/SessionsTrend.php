<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/**
 * منحنى الحصص خلال آخر أربعة أسابيع، مفصولًا بين المُقامة وغير المُقامة.
 *
 * الفصل مقصود: ارتفاع خط "لم تُقَم" مؤشر تشغيلي مبكر يستحق تدخّل الإشراف.
 */
final class SessionsTrend extends ChartWidget
{
    protected ?string $heading = 'الحصص خلال آخر أربعة أسابيع';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $from = CarbonImmutable::now('UTC')->startOfDay()->subDays(27);

        $rows = DB::table('sessions')
            ->whereNull('deleted_at')
            ->where('scheduled_start', '>=', $from)
            ->selectRaw("date_trunc('day', scheduled_start) as day, status, count(*) as total")
            ->groupBy('day', 'status')
            ->get();

        $labels = [];
        $held = [];
        $missed = [];

        $notHeld = ['no_show', 'cancelled_by_student', 'cancelled_by_teacher', 'cancelled_by_school', 'postponed'];

        for ($i = 0; $i < 28; $i++) {
            $day = $from->addDays($i);
            $key = $day->toDateString();
            $labels[] = $day->format('d/m');

            $forDay = $rows->filter(
                static fn (object $r): bool => CarbonImmutable::parse((string) $r->day)->toDateString() === $key,
            );

            $held[] = (int) $forDay
                ->whereIn('status', ['completed', 'in_progress', 'awaiting_review'])
                ->sum('total');

            $missed[] = (int) $forDay->whereIn('status', $notHeld)->sum('total');
        }

        return [
            'datasets' => [
                [
                    'label' => 'أُقيمت',
                    'data' => $held,
                    'borderColor' => 'rgb(16, 185, 129)',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.12)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
                [
                    'label' => 'لم تُقَم',
                    'data' => $missed,
                    'borderColor' => 'rgb(244, 63, 94)',
                    'backgroundColor' => 'rgba(244, 63, 94, 0.10)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['position' => 'bottom', 'labels' => ['usePointStyle' => true]],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
