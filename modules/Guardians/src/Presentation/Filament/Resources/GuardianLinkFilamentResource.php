<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Filament\Resources;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Guardians\Domain\Enums\GuardianRelationship;
use Modules\Guardians\Domain\Models\GuardianLink;
use Modules\Guardians\Presentation\Filament\Resources\Pages\ManageGuardianLinks;

final class GuardianLinkFilamentResource extends Resource
{
    protected static ?string $model = GuardianLink::class;

    protected static bool $shouldRegisterNavigation = true;

    public static function getModelLabel(): string
    {
        return __('guardians::filament.link.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('guardians::filament.link.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('relationship')
                ->label(__('guardians::filament.link.fields.relationship'))
                ->options(collect(GuardianRelationship::cases())->mapWithKeys(
                    fn (GuardianRelationship $relationship): array => [$relationship->value => $relationship->label()],
                )->all())
                ->required(),

            Checkbox::make('is_primary')
                ->label(__('guardians::filament.link.fields.is_primary')),

            Checkbox::make('can_act_for')
                ->label(__('guardians::filament.link.fields.can_act_for')),

            TagsInput::make('visible_sections')
                ->label(__('guardians::filament.link.fields.visible_sections'))
                ->suggestions((array) config('guardians.links.allowed_visible_sections', [])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('guardian_profile_id')
                    ->label(__('guardians::filament.link.fields.guardian'))
                    ->sortable()
                    ->copyable(),
                TextColumn::make('student_profile_id')
                    ->label(__('guardians::filament.link.fields.student'))
                    ->sortable()
                    ->copyable(),
                TextColumn::make('relationship')
                    ->label(__('guardians::filament.link.fields.relationship'))
                    ->badge()
                    ->formatStateUsing(fn (GuardianRelationship $state): string => $state->label()),
                IconColumn::make('is_primary')
                    ->label(__('guardians::filament.link.fields.is_primary'))
                    ->boolean(),
                IconColumn::make('can_act_for')
                    ->label(__('guardians::filament.link.fields.can_act_for'))
                    ->boolean(),
                TextColumn::make('verified_at')
                    ->label(__('guardians::filament.link.fields.verified_at'))
                    ->dateTime()
                    ->placeholder(__('guardians::filament.link.unverified')),
            ])
            ->filters([
                TernaryFilter::make('verified')
                    ->label(__('guardians::filament.link.filters.verified'))
                    ->queries(
                        true: fn ($query) => $query->verified(),
                        false: fn ($query) => $query->whereNull('verified_at'),
                    ),
                SelectFilter::make('relationship')
                    ->label(__('guardians::filament.link.fields.relationship'))
                    ->options(collect(GuardianRelationship::cases())->mapWithKeys(
                        fn (GuardianRelationship $relationship): array => [$relationship->value => $relationship->label()],
                    )->all()),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    /**
     * @return array<string, array<class-string>>
     */
    public static function getPages(): array
    {
        return [
            'index' => ManageGuardianLinks::route('/'),
        ];
    }
}
