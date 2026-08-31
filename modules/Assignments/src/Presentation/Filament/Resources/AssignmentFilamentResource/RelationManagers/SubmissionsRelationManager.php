<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Filament\Resources\AssignmentFilamentResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Assignments\Application\Actions\GradeSubmissionAction;
use Modules\Assignments\Application\Queries\AssignmentAdministrationQueryService;
use Modules\Assignments\Domain\Enums\AssignmentSubmissionStatus;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Assignments\Domain\Models\AssignmentSubmission;
use Shared\Support\BusinessRuleViolation;

final class SubmissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'submissions';

    /** @var array<string, string>|null */
    private ?array $studentLabels = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('assignments::filament.submissions.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student_profile_id')
                    ->label(__('assignments::filament.submissions.student'))
                    ->formatStateUsing(fn (mixed $state): string => $this->studentLabel((string) $state))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('assignments::attributes.status'))
                    ->badge()
                    ->formatStateUsing(fn (AssignmentSubmissionStatus $state): string => $state->label())
                    ->color(fn (AssignmentSubmissionStatus $state): string => $state->color()),
                TextColumn::make('submitted_at')
                    ->label(__('assignments::filament.submissions.submitted_at'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder(__('assignments::messages.not_available'))
                    ->sortable(),
                TextColumn::make('is_late')
                    ->label(__('assignments::filament.submissions.is_late'))
                    ->formatStateUsing(fn (mixed $state): string => (bool) $state
                        ? __('assignments::messages.yes')
                        : __('assignments::messages.no'))
                    ->badge()
                    ->color(fn (mixed $state): string => (bool) $state ? 'danger' : 'gray'),
                TextColumn::make('content')
                    ->label(__('assignments::attributes.content'))
                    ->limit(60)
                    ->placeholder(__('assignments::messages.not_available'))
                    ->toggleable(),
                TextColumn::make('raw_score')
                    ->label(__('assignments::filament.submissions.raw_score'))
                    ->numeric()
                    ->placeholder(__('assignments::messages.not_available')),
                TextColumn::make('penalty_points')
                    ->label(__('assignments::filament.submissions.penalty_points'))
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('score')
                    ->label(__('assignments::attributes.score'))
                    ->numeric()
                    ->placeholder(__('assignments::messages.not_available')),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('assignments::attributes.status'))
                    ->options(collect(AssignmentSubmissionStatus::cases())->mapWithKeys(
                        static fn (AssignmentSubmissionStatus $status): array => [$status->value => $status->label()],
                    )->all()),
            ])
            ->headerActions([])
            ->recordActions([$this->gradeAction()])
            ->bulkActions([])
            ->defaultSort('submitted_at', 'desc');
    }

    private function gradeAction(): Action
    {
        return Action::make('grade')
            ->label(__('assignments::filament.actions.grade'))
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (AssignmentSubmission $record): bool => in_array($record->status, [
                AssignmentSubmissionStatus::Submitted,
                AssignmentSubmissionStatus::Late,
            ], true) && (auth()->user()?->can('grade', $record) ?? false))
            ->schema([
                TextInput::make('score')
                    ->label(__('assignments::filament.submissions.raw_score'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(fn (): int => $this->assignment()->max_score)
                    ->required(),
                Textarea::make('feedback')
                    ->label(__('assignments::attributes.feedback'))
                    ->maxLength(5000),
                Textarea::make('reason')
                    ->label(__('assignments::attributes.reason'))
                    ->required()
                    ->maxLength((int) config('assignments.reason_max_length', 1000)),
            ])
            ->action(function (AssignmentSubmission $record, array $data): void {
                try {
                    app(GradeSubmissionAction::class)->execute(
                        submission: $record,
                        data: ['score' => (int) $data['score'], 'feedback' => $data['feedback'] ?? null],
                        actorId: (string) auth()->id(),
                        reason: (string) $data['reason'],
                    );
                } catch (BusinessRuleViolation $violation) {
                    Notification::make()->title($violation->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title(__('assignments::messages.graded'))
                    ->success()
                    ->send();
            });
    }

    private function studentLabel(string $studentProfileId): string
    {
        if ($this->studentLabels === null) {
            $this->studentLabels = collect(app(AssignmentAdministrationQueryService::class)->submissions(
                (string) $this->assignment()->organization_id,
                (string) $this->assignment()->getKey(),
            ))->mapWithKeys(static fn (array $submission): array => [
                (string) $submission['id'] => (string) $submission['student'],
            ])->all();

            $byStudent = [];

            foreach ($this->assignment()->submissions()->get(['id', 'student_profile_id']) as $submission) {
                $byStudent[(string) $submission->student_profile_id] = $this->studentLabels[(string) $submission->getKey()]
                    ?? (string) $submission->student_profile_id;
            }

            $this->studentLabels = $byStudent;
        }

        return $this->studentLabels[$studentProfileId] ?? $studentProfileId;
    }

    private function assignment(): Assignment
    {
        $record = $this->getOwnerRecord();
        abort_unless($record instanceof Assignment, 404);

        return $record;
    }
}
