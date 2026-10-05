<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Filament\Resources\LevelFilamentResource\Pages;

use Filament\Actions\EditAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Modules\Academics\Application\Queries\AcademicAdministrationQueryService;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Presentation\Filament\Resources\LevelFilamentResource;
use Shared\Support\LocalizedJsonColumn;

final class ViewLevel extends ViewRecord
{
    protected static string $resource = LevelFilamentResource::class;

    /** @var array<string, mixed>|null */
    private ?array $hubData = null;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()->visible(fn (): bool => auth()->user()?->can('update', $this->level()) ?? false)];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('academics::filament.level.hub.overview'))->schema([
                TextEntry::make('code')->label(__('academics::filament.level.fields.code'))->copyable(),
                TextEntry::make('name')->label(__('academics::filament.level.fields.name'))->formatStateUsing(static fn ($state): string => LocalizedJsonColumn::display($state)),
                TextEntry::make('sort_order')->label(__('academics::filament.level.fields.sort_order')),
                TextEntry::make('program')->label(__('academics::filament.level.fields.program'))->state(fn (): string => (string) data_get($this->hub(), 'program.name')),
                TextEntry::make('courses_count')->label(__('academics::filament.level.fields.courses_count'))->state(fn (): int => count((array) data_get($this->hub(), 'courses', []))),
            ])->columns(3),
            Tabs::make(__('academics::filament.level.hub.title'))->tabs([
                Tab::make(__('academics::filament.level.hub.courses'))->icon('heroicon-o-book-open')->schema([
                    RepeatableEntry::make('courses_hub')->hiddenLabel()->placeholder(__('academics::filament.hub.empty'))
                        ->getStateUsing(fn (): array => array_values((array) data_get($this->hub(), 'courses', [])))
                        ->schema([
                            TextEntry::make('code')->label(__('academics::filament.course.fields.code'))->copyable(),
                            TextEntry::make('name')->label(__('academics::filament.course.fields.name')),
                            TextEntry::make('session_mode')->label(__('academics::filament.course.fields.session_mode'))->badge(),
                            TextEntry::make('total_sessions')->label(__('academics::filament.course.fields.total_sessions')),
                            IconEntry::make('is_active')->label(__('academics::filament.course.fields.is_active'))->boolean(),
                        ])->columns(5),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    /** @return array<string, mixed> */
    private function hub(): array
    {
        return $this->hubData ??= app(AcademicAdministrationQueryService::class)->levelHub(
            (string) $this->level()->program?->organization_id,
            (string) $this->level()->getKey(),
        );
    }

    private function level(): Level
    {
        abort_unless($this->record instanceof Level, 404);

        return $this->record;
    }
}
