<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Models\NotificationTemplate;
use Modules\Notifications\Presentation\Filament\Resources\NotificationTemplateResource\Pages;

/**
 * مورد إدارة قوالب الإشعارات — تحكم الأدمن في كل نص وعنوان ومتغيّر وقالب مزوّد
 * لكل حدث × قناة × لغة. القوالب العامة مرجع مشترك للقراءة؛ التخصيص يكون بإنشاء
 * نسخة override خاصة بالمؤسسة تتفوق عليها عند العرض (انظر TemplateRenderer).
 */
final class NotificationTemplateResource extends Resource
{
    protected static ?string $model = NotificationTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 72;

    protected static ?string $recordTitleAttribute = 'event_key';

    public static function getNavigationGroup(): string
    {
        return __('notifications::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('notifications::navigation.template.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('notifications::navigation.template.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('notifications::fields.routing'))
                ->description(__('notifications::templates_admin.routing_hint'))
                ->schema([
                    Select::make('event_key')
                        ->label(__('notifications::fields.event_name'))
                        ->options(self::eventOptions())
                        ->searchable()
                        ->required(),
                    Select::make('channel')
                        ->label(__('notifications::fields.channel'))
                        ->options(collect(Channel::cases())
                            ->mapWithKeys(fn (Channel $c): array => [$c->value => $c->label()])
                            ->all())
                        ->required(),
                    Select::make('locale')
                        ->label(__('notifications::fields.locale'))
                        ->options(self::localeOptions())
                        ->required(),
                    Toggle::make('is_active')
                        ->label(__('notifications::fields.is_active'))
                        ->default(true)
                        ->inline(false),
                ])
                ->columns(2)
                ->columnSpanFull(),
            Section::make(__('notifications::fields.content'))
                ->schema([
                    TextInput::make('subject')
                        ->label(__('notifications::fields.subject'))
                        ->maxLength(255)
                        ->helperText(__('notifications::templates_admin.subject_hint'))
                        ->columnSpanFull(),
                    Textarea::make('body')
                        ->label(__('notifications::fields.body'))
                        ->required()
                        ->rows(4)
                        ->helperText(__('notifications::templates_admin.body_hint'))
                        ->columnSpanFull(),
                    TagsInput::make('parameters')
                        ->label(__('notifications::fields.parameters'))
                        ->helperText(__('notifications::templates_admin.parameters_hint'))
                        ->columnSpanFull(),
                    TextInput::make('provider_template_name')
                        ->label(__('notifications::fields.provider_template_name'))
                        ->maxLength(255)
                        ->helperText(__('notifications::templates_admin.provider_template_hint'))
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event_key')
                    ->label(__('notifications::fields.event_name'))
                    ->formatStateUsing(fn (?string $state): string => self::eventLabel($state))
                    ->description(fn (NotificationTemplate $record): string => (string) $record->event_key)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('channel')
                    ->label(__('notifications::fields.channel'))
                    ->formatStateUsing(fn ($state): string => Channel::tryFrom((string) $state)?->label() ?? (string) $state)
                    ->badge()
                    ->sortable(),
                TextColumn::make('locale')
                    ->label(__('notifications::fields.locale'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('scope')
                    ->label(__('notifications::fields.scope'))
                    ->badge()
                    ->state(fn (NotificationTemplate $record): string => $record->isGlobal()
                        ? __('notifications::templates_admin.scope_global')
                        : __('notifications::templates_admin.scope_organization'))
                    ->color(fn (NotificationTemplate $record): string => $record->isGlobal() ? 'gray' : 'success'),
                TextColumn::make('subject')
                    ->label(__('notifications::fields.subject'))
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('provider_template_name')
                    ->label(__('notifications::fields.provider_template_name'))
                    ->limit(24)
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label(__('notifications::fields.is_active'))
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label(__('notifications::fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('event_key')
                    ->label(__('notifications::fields.event_name'))
                    ->options(self::eventOptions()),
                SelectFilter::make('channel')
                    ->label(__('notifications::fields.channel'))
                    ->options(collect(Channel::cases())
                        ->mapWithKeys(fn (Channel $c): array => [$c->value => $c->label()])
                        ->all()),
                SelectFilter::make('locale')
                    ->label(__('notifications::fields.locale'))
                    ->options(self::localeOptions()),
                TernaryFilter::make('is_active')
                    ->label(__('notifications::fields.is_active')),
                TernaryFilter::make('scope')
                    ->label(__('notifications::fields.scope'))
                    ->placeholder(__('notifications::templates_admin.scope_all'))
                    ->trueLabel(__('notifications::templates_admin.scope_organization'))
                    ->falseLabel(__('notifications::templates_admin.scope_global'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('organization_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('organization_id'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                // القالب العام يُقرأ ولا يُعدَّل، فبدون ViewAction كان صفّه بلا أي
                // إجراء صالح: Edit و Delete تمنعهما السياسة، والنتيجة صفحة تبدو
                // معطّلة لأن كل القوالب المزروعة عامة.
                ViewAction::make()
                    ->visible(fn (NotificationTemplate $record): bool => $record->isGlobal()),
                EditAction::make()
                    ->visible(fn (NotificationTemplate $record): bool => !$record->isGlobal()),
                self::cloneToOrganizationAction(),
                DeleteAction::make()
                    ->visible(fn (NotificationTemplate $record): bool => !$record->isGlobal()),
            ])
            ->defaultSort('event_key');
    }

    /**
     * القالب العام مرئي لكل مؤسسة؛ قوالب المؤسسة مرئية لها وحدها.
     *
     * @return Builder<NotificationTemplate>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<NotificationTemplate> $query */
        $query = parent::getEloquentQuery();
        $organizationId = data_get(auth()->user(), 'organization_id');

        if (!is_string($organizationId) || $organizationId === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query->visibleToOrganization($organizationId);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationTemplates::route('/'),
            'create' => Pages\CreateNotificationTemplate::route('/create'),
            'edit' => Pages\EditNotificationTemplate::route('/{record}/edit'),
        ];
    }

    /**
     * إنشاء نسخة override خاصة بالمؤسسة من قالب عام — لتخصيص النص دون المساس
     * بالافتراضي المشترك. تظهر فقط على القوالب العامة التي لا تملك المؤسسة
     * نسخة منها بعد.
     */
    private static function cloneToOrganizationAction(): Action
    {
        return Action::make('clone_to_organization')
            ->label(__('notifications::templates_admin.clone_action'))
            ->icon('heroicon-m-document-duplicate')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading(__('notifications::templates_admin.clone_heading'))
            ->modalDescription(__('notifications::templates_admin.clone_description'))
            ->authorize(fn (): bool => auth()->user()?->can('create', NotificationTemplate::class) ?? false)
            ->visible(fn (NotificationTemplate $record): bool => $record->isGlobal()
                && !self::organizationOverrideExists($record))
            ->action(function (NotificationTemplate $record): void {
                $organizationId = (string) data_get(auth()->user(), 'organization_id');

                if ($organizationId === '' || self::organizationOverrideExists($record)) {
                    Notification::make()
                        ->title(__('notifications::templates_admin.clone_conflict'))
                        ->danger()
                        ->send();

                    return;
                }

                $copy = NotificationTemplate::query()->create([
                    'organization_id' => $organizationId,
                    'event_key' => $record->event_key,
                    'channel' => $record->channel,
                    'locale' => $record->locale,
                    'subject' => $record->subject,
                    'body' => $record->body,
                    'provider_template_name' => $record->provider_template_name,
                    'parameters' => $record->parameters,
                    'is_active' => $record->is_active,
                ]);

                Notification::make()
                    ->title(__('notifications::templates_admin.clone_done'))
                    ->success()
                    ->send();

                // الهدف من النسخ هو التعديل — نفتح النسخة الجديدة مباشرة بدل
                // إعادة الأدمن إلى قائمة عليه أن يبحث فيها عن صفّه من جديد.
                redirect(self::getUrl('edit', ['record' => $copy]));
            });
    }

    private static function organizationOverrideExists(NotificationTemplate $record): bool
    {
        $organizationId = data_get(auth()->user(), 'organization_id');

        if (!is_string($organizationId) || $organizationId === '') {
            return false;
        }

        return NotificationTemplate::query()
            ->where('organization_id', $organizationId)
            ->where('event_key', $record->event_key)
            ->where('channel', $record->channel)
            ->where('locale', $record->locale)
            ->exists();
    }

    /**
     * أحداث المرحلة الأولى المعرّفة في الإعداد، بأسمائها المعرّبة.
     *
     * @return array<string, string>
     */
    private static function eventOptions(): array
    {
        $options = [];

        foreach (array_keys((array) config('notifications.events', [])) as $event) {
            $options[(string) $event] = self::eventLabel((string) $event);
        }

        asort($options);

        return $options;
    }

    /**
     * حدث بلا ترجمة يظهر بمفتاحه بدل أن يختفي من القائمة.
     */
    private static function eventLabel(?string $event): string
    {
        if ($event === null || $event === '') {
            return '—';
        }

        $translation = __('notifications::events.'.$event);

        return is_string($translation) && !str_starts_with($translation, 'notifications::')
            ? $translation
            : $event;
    }

    /**
     * @return array<string, string>
     */
    private static function localeOptions(): array
    {
        /** @var list<string> $supported */
        $supported = (array) config('notifications.localization.supported', ['ar', 'en']);

        return collect($supported)
            ->mapWithKeys(fn (string $locale): array => [
                $locale => __('notifications::templates_admin.locale_'.$locale),
            ])
            ->all();
    }
}
