<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Actions;

use Modules\Academics\Domain\Models\ProgramCategory;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class ArchiveProgramCategoryAction
{
    public function __construct(
        private Transaction $transaction,
        private AuditRecorder $audit,
    ) {}

    public function execute(ProgramCategory $category, string $actorId, string $reason): ProgramCategory
    {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make('academics.reason_required', 'academics::errors.reason_required');
        }

        return $this->transaction->run(function () use ($category, $actorId, $reason): ProgramCategory {
            $category->delete();
            $this->audit->record(
                organizationId: (string) $category->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'academics.category_archived',
                auditableType: 'program_categories',
                auditableId: (string) $category->getKey(),
                oldValues: ['archived_at' => null],
                newValues: ['archived_at' => now()->utc()->toIso8601String()],
                reason: trim($reason),
            );

            return $category;
        });
    }
}
