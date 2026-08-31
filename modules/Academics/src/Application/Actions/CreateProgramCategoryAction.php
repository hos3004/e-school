<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Actions;

use Illuminate\Support\Arr;
use Modules\Academics\Domain\Models\Program;
use Modules\Academics\Domain\Models\ProgramCategory;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class CreateProgramCategoryAction
{
    public function __construct(
        private Transaction $transaction,
        private AuditRecorder $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(array $data, string $actorId, string $reason): ProgramCategory
    {
        $organizationId = (string) $data['organization_id'];
        $programId = isset($data['program_id']) && $data['program_id'] !== '' ? (string) $data['program_id'] : null;
        $parentId = isset($data['parent_id']) && $data['parent_id'] !== '' ? (string) $data['parent_id'] : null;
        $this->assertReasonGiven($reason);
        $this->assertCodeAvailable($organizationId, (string) $data['code']);
        $this->assertProgramAndParentBelong($organizationId, $programId, $parentId);

        return $this->transaction->run(function () use ($data, $actorId, $reason): ProgramCategory {
            $category = new ProgramCategory;
            $category->fill(Arr::except($data, ['reason']));
            $category->save();

            $this->audit->record(
                organizationId: (string) $category->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'academics.category_created',
                auditableType: 'program_categories',
                auditableId: (string) $category->getKey(),
                oldValues: null,
                newValues: Arr::only($category->getAttributes(), ['program_id', 'parent_id', 'code', 'name', 'is_active']),
                reason: trim($reason),
            );

            return $category;
        });
    }

    private function assertCodeAvailable(string $organizationId, string $code): void
    {
        if (ProgramCategory::query()->withTrashed()->where('organization_id', $organizationId)->where('code', $code)->exists()) {
            throw BusinessRuleViolation::make('academics.category_code_taken', 'academics::errors.category_code_taken', ['code' => $code]);
        }
    }

    private function assertProgramAndParentBelong(string $organizationId, ?string $programId, ?string $parentId): void
    {
        if ($programId !== null && !Program::query()->whereKey($programId)->where('organization_id', $organizationId)->exists()) {
            throw BusinessRuleViolation::make('academics.program_not_found', 'academics::errors.program_not_found');
        }

        if ($parentId !== null && !ProgramCategory::query()->whereKey($parentId)->where('organization_id', $organizationId)->exists()) {
            throw BusinessRuleViolation::make('academics.category_parent_invalid', 'academics::errors.category_parent_invalid');
        }
    }

    private function assertReasonGiven(string $reason): void
    {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make('academics.reason_required', 'academics::errors.reason_required');
        }
    }
}
