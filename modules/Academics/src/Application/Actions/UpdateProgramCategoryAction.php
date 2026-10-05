<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Actions;

use Illuminate\Support\Arr;
use Modules\Academics\Domain\Models\ProgramCategory;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class UpdateProgramCategoryAction
{
    public function __construct(
        private Transaction $transaction,
        private AuditRecorder $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(ProgramCategory $category, array $data, string $actorId, string $reason): ProgramCategory
    {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make('academics.reason_required', 'academics::errors.reason_required');
        }

        $data = Arr::except($data, ['organization_id', 'program_id', 'category_id', 'reason']);
        if (isset($data['code']) && (string) $data['code'] !== (string) $category->code) {
            $taken = ProgramCategory::query()->withTrashed()
                ->where('organization_id', (string) $category->organization_id)
                ->where('code', (string) $data['code'])
                ->whereKeyNot((string) $category->getKey())
                ->exists();
            if ($taken) {
                throw BusinessRuleViolation::make('academics.category_code_taken', 'academics::errors.category_code_taken', ['code' => (string) $data['code']]);
            }
        }

        if (isset($data['parent_id']) && (string) $data['parent_id'] === (string) $category->getKey()) {
            throw BusinessRuleViolation::make('academics.category_parent_invalid', 'academics::errors.category_parent_invalid');
        }

        $fields = array_keys($data);
        $oldValues = Arr::only($category->getAttributes(), $fields);

        return $this->transaction->run(function () use ($category, $data, $fields, $oldValues, $actorId, $reason): ProgramCategory {
            $category->fill($data);
            $category->save();

            if ($category->wasChanged()) {
                $this->audit->record(
                    organizationId: (string) $category->organization_id,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'academics.category_updated',
                    auditableType: 'program_categories',
                    auditableId: (string) $category->getKey(),
                    oldValues: $oldValues,
                    newValues: Arr::only($category->getAttributes(), $fields),
                    reason: trim($reason),
                );
            }

            return $category;
        });
    }
}
