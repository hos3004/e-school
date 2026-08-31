<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Filament\Resources\CourseMaterialResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Content\Application\Actions\DeleteCourseMaterialAction;
use Modules\Content\Application\Actions\TransitionMaterialStatusAction;
use Modules\Content\Application\Queries\ContentAdministrationQueryService;
use Modules\Content\Domain\Enums\MaterialStatus;
use Modules\Content\Domain\Enums\MaterialType;
use Modules\Content\Domain\Models\CourseMaterial;
use Modules\Content\Presentation\Filament\Resources\CourseMaterialResource;
use Shared\Support\BusinessRuleViolation;

final class ViewCourseMaterial extends ViewRecord
{
    protected static string $resource = CourseMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->statusAction(MaterialStatus::Published, 'publish'),
            $this->statusAction(MaterialStatus::Unpublished, 'unpublish'),
            $this->archiveAction(),
            EditAction::make()
                ->visible(fn (): bool => auth()->user()?->can('update', $this->material()) ?? false),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('content::messages.overview'))
                ->schema([
                    TextEntry::make('title')
                        ->label(__('content::fields.title'))
                        ->formatStateUsing(fn (mixed $state): string => $this->localized($state)),
                    TextEntry::make('course')
                        ->label(__('content::fields.course'))
                        ->state(fn (CourseMaterial $record): string => app(ContentAdministrationQueryService::class)
                            ->courseLabel((string) $record->organization_id, (string) $record->course_id)),
                    TextEntry::make('type')
                        ->label(__('content::fields.type'))
                        ->badge()
                        ->formatStateUsing(fn (MaterialType $state): string => $state->label()),
                    TextEntry::make('status')
                        ->label(__('content::fields.status'))
                        ->badge()
                        ->formatStateUsing(fn (MaterialStatus $state): string => $state->label())
                        ->color(fn (MaterialStatus $state): string => $state->color()),
                    TextEntry::make('revision')->label(__('content::fields.revision')),
                    TextEntry::make('display_order')->label(__('content::fields.display_order')),
                    IconEntry::make('currently_visible')
                        ->label(__('content::fields.visible_now'))
                        ->state(fn (CourseMaterial $record): bool => $record->isCurrentlyVisible())
                        ->boolean(),
                    TextEntry::make('source_reference')
                        ->label(__('content::fields.source_reference'))
                        ->state(fn (CourseMaterial $record): string => $record->type === MaterialType::Link
                            ? (string) $record->external_url
                            : basename((string) $record->path)),
                    TextEntry::make('visible_from')->label(__('content::fields.visible_from'))->dateTime(),
                    TextEntry::make('visible_to')->label(__('content::fields.visible_to'))->dateTime(),
                    TextEntry::make('published_at')->label(__('content::fields.published_at'))->dateTime(),
                    TextEntry::make('description')
                        ->label(__('content::fields.description'))
                        ->formatStateUsing(fn (mixed $state): string => $this->localized($state))
                        ->columnSpanFull(),
                ])->columns(3),

            Section::make(__('content::messages.version_history'))
                ->icon('heroicon-o-clock')
                ->schema([
                    RepeatableEntry::make('versions')
                        ->hiddenLabel()
                        ->placeholder(__('content::messages.no_versions'))
                        ->getStateUsing(fn (): array => app(ContentAdministrationQueryService::class)->versions(
                            (string) $this->material()->organization_id,
                            (string) $this->material()->getKey(),
                        ))
                        ->schema([
                            TextEntry::make('revision')->label(__('content::fields.revision')),
                            TextEntry::make('status')->label(__('content::fields.status'))->badge(),
                            TextEntry::make('reason')->label(__('content::fields.reason')),
                            TextEntry::make('actor')->label(__('content::fields.changed_by')),
                            TextEntry::make('created_at')->label(__('content::fields.changed_at'))->dateTime(),
                        ])->columns(5),
                ]),
        ]);
    }

    private function statusAction(MaterialStatus $target, string $name): Action
    {
        return Action::make($name)
            ->label(__('content::messages.'.$name))
            ->icon($target === MaterialStatus::Published ? 'heroicon-o-eye' : 'heroicon-o-eye-slash')
            ->color($target === MaterialStatus::Published ? 'success' : 'warning')
            ->visible(fn (): bool => $this->material()->status->canTransitionTo($target)
                && (auth()->user()?->can('publish', $this->material()) ?? false))
            ->requiresConfirmation()
            ->schema([$this->reasonField()])
            ->action(function (array $data) use ($target, $name): void {
                $this->executeSafely(function () use ($target, $data): void {
                    app(TransitionMaterialStatusAction::class)->execute(
                        $this->material(),
                        $target,
                        (string) $data['reason'],
                        (string) auth()->id(),
                    );
                }, $name.'ed');
            });
    }

    private function archiveAction(): Action
    {
        return Action::make('archive')
            ->label(__('content::messages.archive'))
            ->icon('heroicon-o-archive-box')
            ->color('danger')
            ->visible(fn (): bool => auth()->user()?->can('delete', $this->material()) ?? false)
            ->requiresConfirmation()
            ->schema([$this->reasonField()])
            ->action(function (array $data): void {
                try {
                    app(DeleteCourseMaterialAction::class)->execute(
                        $this->material(),
                        (string) $data['reason'],
                        (string) auth()->id(),
                    );
                } catch (BusinessRuleViolation $violation) {
                    Notification::make()->title($violation->getMessage())->danger()->send();

                    return;
                }

                $this->redirect(CourseMaterialResource::getUrl());
            });
    }

    private function reasonField(): Textarea
    {
        return Textarea::make('reason')
            ->label(__('content::fields.reason'))
            ->required()
            ->maxLength((int) config('content.reason_max_length'));
    }

    private function executeSafely(callable $callback, string $notification): void
    {
        try {
            $callback();
        } catch (BusinessRuleViolation $violation) {
            Notification::make()->title($violation->getMessage())->danger()->send();

            return;
        }

        $this->record->refresh();
        Notification::make()->title(__('content::messages.'.$notification))->success()->send();
    }

    private function material(): CourseMaterial
    {
        abort_unless($this->record instanceof CourseMaterial, 404);

        return $this->record;
    }

    private function localized(mixed $state): string
    {
        if (!is_array($state)) {
            return (string) ($state ?? '');
        }

        return (string) ($state[app()->getLocale()] ?? $state['ar'] ?? $state['en'] ?? reset($state));
    }
}
