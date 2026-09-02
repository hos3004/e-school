<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Filament\Resources;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Messaging\Application\Actions\FlagMessageAction;
use Modules\Messaging\Domain\Models\Message;
use Shared\Concerns\ScopesFilamentToOrganization;
use Shared\Filament\RecordOriginGuide;

/**
 * مورد إشراف على الرسائل في لوحة الإدارة.
 */
final class MessageResource extends Resource
{
    use ScopesFilamentToOrganization;

    protected static ?string $model = Message::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static ?int $navigationSort = 70;

    // الخانة معلنة هنا لا في الصنف الأب: `$navigationParentItem` في Filament
    // مشتركة بين كل الموارد، فبلا إعادة إعلانها يدهس آخرُ إسناد ما قبله.
    // القيمة نفسها تُضبط مركزيًا في App\Filament\AdminNavigation.
    protected static ?string $navigationParentItem = null;

    public static function getNavigationGroup(): string
    {
        return __('messaging::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('messaging::navigation.message.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('messaging::navigation.message.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            RichEditor::make('body')
                ->label(__('messaging::fields.body'))
                ->required()
                ->columnSpanFull(),
            Select::make('conversation_id')
                ->label(__('messaging::fields.conversation'))
                ->relationship('conversation', 'subject')
                ->searchable()
                ->required(),
            Toggle::make('is_flagged')
                ->label(__('messaging::fields.is_flagged')),
            Textarea::make('flagged_reason')
                ->label(__('messaging::fields.flagged_reason'))
                ->maxLength(1000),
        ]);
    }

    public static function table(Table $table): Table
    {
        return RecordOriginGuide::for(
            $table,
            'messaging::origin.message',
            'heroicon-o-envelope',
        )
            ->columns([
                TextColumn::make('id')
                    ->label(__('messaging::fields.id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('body')
                    ->label(__('messaging::fields.body'))
                    ->limit(60)
                    ->searchable(),
                TextColumn::make('conversation.subject')
                    ->label(__('messaging::fields.conversation'))
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('user_id')
                    ->label(__('messaging::fields.sender'))
                    ->copyable()
                    ->toggleable(),
                IconColumn::make('is_flagged')
                    ->label(__('messaging::fields.is_flagged'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('messaging::fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_flagged')
                    ->label(__('messaging::fields.is_flagged')),
            ])
            ->recordActions([self::flagAction()])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * تعليم رسالة للمراجعة — أداة الإشراف الوحيدة على محتوى المحادثات.
     *
     * `FlagMessageAction` وسياسة `flag` كانتا موجودتين بلا زر، فكان عمود
     * «معلَّمة» في هذا الجدول يعرض حالةً لا سبيل لأحد أن يصنعها من اللوحة.
     */
    public static function flagAction(): Action
    {
        return Action::make('flag')
            ->label(__('messaging::fields.flag'))
            ->icon('heroicon-m-flag')
            ->color('danger')
            ->authorize('flag')
            ->visible(fn (Message $record): bool => !$record->is_flagged)
            ->form([
                Textarea::make('reason')
                    ->label(__('messaging::fields.flagged_reason'))
                    ->required()
                    ->minLength(3)
                    ->maxLength(1000),
            ])
            ->action(function (Message $record, array $data): void {
                app(FlagMessageAction::class)->execute(
                    $record,
                    (string) auth()->id(),
                    (string) $data['reason'],
                );

                Notification::make()
                    ->title(__('messaging::fields.flagged_notice'))
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => MessageResource\Pages\ListMessages::route('/'),
        ];
    }
}
