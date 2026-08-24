<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Filament\Resources\SessionResource\Pages;

use Carbon\CarbonImmutable;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Presentation\Filament\Resources\SessionResource;

final class CalendarSessions extends Page
{
    protected static string $resource = SessionResource::class;

    protected string $view = 'sessions::filament.calendar-sessions';

    public string $calendarMode = 'week';

    public string $calendarDate;

    public string $groupFilter = '';

    public string $teacherFilter = '';

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->calendarDate = now('UTC')->toDateString();
    }

    public function previousPeriod(): void
    {
        $this->calendarDate = $this->calendarMode === 'month'
            ? $this->periodAnchor()->subMonth()->toDateString()
            : $this->periodAnchor()->subWeek()->toDateString();
    }

    public function nextPeriod(): void
    {
        $this->calendarDate = $this->calendarMode === 'month'
            ? $this->periodAnchor()->addMonth()->toDateString()
            : $this->periodAnchor()->addWeek()->toDateString();
    }

    public function currentPeriod(): void
    {
        $this->calendarDate = now('UTC')->toDateString();
    }

    public function getSessions(): array
    {
        $organizationId = data_get(auth()->user(), 'organization_id');

        [$periodStart, $periodEnd] = $this->periodBounds();

        return Session::query()
            ->when(is_string($organizationId) && $organizationId !== '', fn (Builder $query): Builder => $query->where('organization_id', $organizationId))
            ->when($this->groupFilter !== '', fn (Builder $query): Builder => $query->where('group_id', $this->groupFilter))
            ->when($this->teacherFilter !== '', fn (Builder $query): Builder => $query->where('staff_profile_id', $this->teacherFilter))
            ->when($this->statusFilter !== '', fn (Builder $query): Builder => $query->where('status', $this->statusFilter))
            ->whereBetween('scheduled_start', [$periodStart, $periodEnd])
            ->orderBy('scheduled_start')
            ->get(['id', 'title', 'scheduled_start', 'scheduled_end', 'status', 'group_id', 'staff_profile_id'])
            ->map(fn (Session $session): array => [
                'id' => (string) $session->getKey(),
                'title' => is_array($session->title) ? (string) ($session->title[app()->getLocale()] ?? reset($session->title) ?: '') : (string) $session->title,
                'start' => $session->scheduled_start?->format('Y-m-d H:i'),
                'end' => $session->scheduled_end?->format('H:i'),
                'status' => $session->status->label(),
                'group' => (string) $session->group_id,
                'teacher' => (string) $session->staff_profile_id,
            ])->all();
    }

    public function sessionUrl(string $id): string
    {
        return SessionResource::getUrl('view', ['record' => $id]);
    }

    /** @return array<string, string> */
    public function groupOptions(): array
    {
        return $this->filterOptions('group_id');
    }

    /** @return array<string, string> */
    public function teacherOptions(): array
    {
        return $this->filterOptions('staff_profile_id');
    }

    /** @return array<string, string> */
    public function statusOptions(): array
    {
        return collect(SessionStatus::cases())
            ->mapWithKeys(fn ($status): array => [$status->value => $status->label()])
            ->all();
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function periodBounds(): array
    {
        $anchor = $this->periodAnchor();

        return $this->calendarMode === 'month'
            ? [$anchor->startOfMonth(), $anchor->endOfMonth()]
            : [$anchor->startOfWeek(), $anchor->endOfWeek()];
    }

    private function periodAnchor(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->calendarDate ?: now('UTC')->toDateString(), 'UTC');
    }

    /** @return array<string, string> */
    private function filterOptions(string $column): array
    {
        $organizationId = data_get(auth()->user(), 'organization_id');

        return Session::query()
            ->when(is_string($organizationId) && $organizationId !== '', fn (Builder $query): Builder => $query->where('organization_id', $organizationId))
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column, $column)
            ->mapWithKeys(fn ($value): array => [(string) $value => (string) $value])
            ->all();
    }
}
