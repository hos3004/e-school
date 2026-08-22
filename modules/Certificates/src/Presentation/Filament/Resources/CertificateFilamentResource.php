<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Filament\Resources;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Certificates\Domain\Models\Certificate;

/**
 * مورد إدارة الشهادات الصادرة في لوحة الإدارة.
 *
 * الشهادات وثائق صادرة — تُعرض وتُسحب فقط، ولا تعديل حر عليها هنا.
 */
final class CertificateFilamentResource extends Resource
{
    protected static ?string $model = Certificate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static \UnitEnum|string|null $navigationGroup = 'التعلّم';

    protected static ?int $navigationSort = 53;

    public static function getModelLabel(): string
    {
        return __('certificates::navigation.certificate.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('certificates::navigation.certificate.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('certificates::fields.issue'))
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('organization_id')
                            ->label(__('certificates::fields.organization'))
                            ->required()
                            ->length(26),
                        TextInput::make('student_profile_id')
                            ->label(__('certificates::fields.student'))
                            ->required()
                            ->length(26),
                        TextInput::make('certificate_template_id')
                            ->label(__('certificates::fields.template'))
                            ->maxLength(26),
                        TextInput::make('program_id')
                            ->label(__('certificates::fields.program'))
                            ->maxLength(26),
                        TextInput::make('enrollment_id')
                            ->label(__('certificates::fields.enrollment'))
                            ->maxLength(26),
                        DateTimePicker::make('issued_at')
                            ->label(__('certificates::fields.issued_at'))
                            ->required(),
                        DateTimePicker::make('expires_at')
                            ->label(__('certificates::fields.expires_at')),
                    ]),
                    KeyValue::make('title')
                        ->label(__('certificates::fields.title'))
                        ->keyLabel(__('certificates::fields.locale'))
                        ->valueLabel(__('certificates::fields.value'))
                        ->required()
                        ->columnSpanFull(),
                    Textarea::make('metadata')
                        ->label(__('certificates::fields.metadata'))
                        ->formatStateUsing(fn ($state): ?string => is_array($state)
                            ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                            : (string) $state)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('serial_number')
                    ->label(__('certificates::fields.serial_number'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('title')
                    ->label(__('certificates::fields.title'))
                    ->formatStateUsing(fn ($state): string => is_array($state)
                        ? ($state[app()->getLocale()] ?? reset($state))
                        : (string) $state)
                    ->limit(40),
                TextColumn::make('student_profile_id')
                    ->label(__('certificates::fields.student'))
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('issued_at')
                    ->label(__('certificates::fields.issued_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label(__('certificates::fields.expires_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('deleted_at')
                    ->label(__('certificates::fields.revoked_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('issued_at', direction: 'desc');
    }
}
