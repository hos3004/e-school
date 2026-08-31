<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Filament\Resources\Pages;

use App\Application\Queries\ProfileAdministrationQueryService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Modules\Guardians\Application\Actions\LinkStudentToGuardian;
use Modules\Guardians\Application\Queries\GuardianAdministrationQueryService;
use Modules\Guardians\Domain\Enums\ContactChannel;
use Modules\Guardians\Domain\Enums\GuardianRelationship;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Modules\Guardians\Presentation\Filament\Resources\GuardianProfileFilamentResource;
use Shared\Support\BusinessRuleViolation;

final class ViewGuardianProfile extends ViewRecord
{
    protected static string $resource = GuardianProfileFilamentResource::class;

    /** @var array<string, mixed>|null */
    private ?array $hubData = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('link_student')
                ->label(__('guardians::admin.actions.link_student'))
                ->icon('heroicon-o-user-plus')
                ->visible(fn (): bool => auth()->user()?->can('linkStudents', $this->record) ?? false)
                ->schema([
                    Select::make('student_profile_id')
                        ->label(__('guardians::admin.fields.student'))
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => app(ProfileAdministrationQueryService::class)->studentOptions(
                            (string) $this->guardian()->organization_id,
                            $search,
                        ))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => is_string($value)
                            ? app(ProfileAdministrationQueryService::class)->studentOptionLabel(
                                (string) $this->guardian()->organization_id,
                                $value,
                            )
                            : null)
                        ->required(),
                    Select::make('relationship')
                        ->label(__('guardians::filament.link.fields.relationship'))
                        ->options(collect(GuardianRelationship::cases())->mapWithKeys(
                            fn (GuardianRelationship $relationship): array => [$relationship->value => $relationship->label()],
                        )->all())
                        ->required(),
                    Toggle::make('is_primary')
                        ->label(__('guardians::filament.link.fields.is_primary')),
                    Toggle::make('can_act_for')
                        ->label(__('guardians::filament.link.fields.can_act_for')),
                    Select::make('visible_sections')
                        ->label(__('guardians::filament.link.fields.visible_sections'))
                        ->options(collect((array) config('guardians.links.allowed_visible_sections'))->mapWithKeys(
                            static fn (string $section): array => [$section => __('guardians::admin.sections.'.$section)],
                        )->all())
                        ->multiple()
                        ->default((array) config('guardians.links.default_visible_sections')),
                    Textarea::make('reason')
                        ->label(__('guardians::admin.fields.reason'))
                        ->maxLength(2000)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->authorize('linkStudents', $this->record);

                    try {
                        app(LinkStudentToGuardian::class)->execute(
                            guardianProfileId: (string) $this->record->getKey(),
                            studentProfileId: (string) $data['student_profile_id'],
                            data: [
                                'relationship' => GuardianRelationship::from((string) $data['relationship']),
                                'is_primary' => (bool) ($data['is_primary'] ?? false),
                                'can_act_for' => (bool) ($data['can_act_for'] ?? false),
                                'visible_sections' => $data['visible_sections'] ?? [],
                            ],
                            actorId: (string) auth()->id(),
                            reason: (string) $data['reason'],
                        );
                    } catch (BusinessRuleViolation $violation) {
                        Notification::make()->title($violation->getMessage())->danger()->send();

                        return;
                    }

                    $this->hubData = null;
                    Notification::make()
                        ->title(__('guardians::admin.actions.linked'))
                        ->success()
                        ->send();
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('guardians::admin.hub.overview'))
                ->icon('heroicon-o-identification')
                ->schema([
                    TextEntry::make('account_name')
                        ->label(__('guardians::admin.fields.name'))
                        ->state(fn (GuardianProfile $record): string => app(GuardianAdministrationQueryService::class)->accountName($record)),
                    TextEntry::make('occupation')
                        ->label(__('guardians::filament.profile.fields.occupation')),
                    TextEntry::make('preferred_contact_channel')
                        ->label(__('guardians::filament.profile.fields.preferred_contact_channel'))
                        ->badge()
                        ->formatStateUsing(fn (?ContactChannel $state): ?string => $state?->label()),
                    TextEntry::make('national_id_last4')
                        ->label(__('guardians::filament.profile.fields.national_id_last4')),
                    TextEntry::make('created_at')
                        ->label(__('guardians::filament.common.created_at'))
                        ->dateTime(),
                ])->columns(3),

            Tabs::make(__('guardians::admin.hub.title'))
                ->persistTabInQueryString('guardian-hub')
                ->tabs([
                    Tab::make(__('guardians::admin.hub.account'))
                        ->icon('heroicon-o-user-circle')
                        ->schema([
                            $this->repeatable('account', [
                                TextEntry::make('name')->label(__('guardians::admin.fields.name')),
                                TextEntry::make('username')->label(__('guardians::admin.fields.username')),
                                TextEntry::make('email')->label(__('guardians::admin.fields.email')),
                                TextEntry::make('phone')->label(__('guardians::admin.fields.phone')),
                                TextEntry::make('status')->label(__('guardians::admin.fields.status'))->badge(),
                            ]),
                        ]),
                    Tab::make(__('guardians::admin.hub.students'))
                        ->icon('heroicon-o-user-group')
                        ->schema([
                            $this->repeatable('students', [
                                TextEntry::make('student')->label(__('guardians::admin.fields.student')),
                                TextEntry::make('student_code')->label(__('guardians::admin.fields.student_code'))->copyable(),
                                TextEntry::make('relationship')->label(__('guardians::filament.link.fields.relationship'))->badge(),
                                TextEntry::make('is_primary')->label(__('guardians::filament.link.fields.is_primary'))->badge(),
                                TextEntry::make('can_act_for')->label(__('guardians::filament.link.fields.can_act_for'))->badge(),
                                TextEntry::make('verified_at')->label(__('guardians::filament.link.fields.verified_at'))->dateTime(),
                                TextEntry::make('visible_sections')->label(__('guardians::filament.link.fields.visible_sections'))->columnSpanFull(),
                            ]),
                        ]),
                ])->columnSpanFull(),
        ]);
    }

    /** @param array<int, TextEntry> $schema */
    private function repeatable(string $section, array $schema): RepeatableEntry
    {
        return RepeatableEntry::make($section.'_hub')
            ->hiddenLabel()
            ->placeholder(__('guardians::admin.hub.empty'))
            ->getStateUsing(fn (GuardianProfile $record): array => $this->hub($record, $section))
            ->schema($schema)
            ->columns(3);
    }

    /** @return array<int, array<string, mixed>> */
    private function hub(GuardianProfile $record, string $section): array
    {
        $this->hubData ??= app(GuardianAdministrationQueryService::class)->profileHub(
            (string) $record->organization_id,
            (string) $record->getKey(),
        );

        $data = $this->hubData[$section] ?? [];

        return is_array($data) ? array_values($data) : [];
    }

    private function guardian(): GuardianProfile
    {
        $record = $this->record;
        abort_unless($record instanceof GuardianProfile, 404);

        return $record;
    }
}
