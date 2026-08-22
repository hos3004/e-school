<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\Academics\Domain\Events\LevelsReordered;
use Modules\Academics\Domain\Models\Level;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إعادة ترتيب مستويات برنامج — يُمرَّر ترتيب كامل بمعرّفات المستويات.
 *
 * القاعدة: كل المعرّفات يجب أن تنتمي إلى البرنامج نفسه، وإلا رُفض الطلب.
 */
final readonly class ReorderLevelsAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param  string  $programId  معرّف البرنامج
     * @param  list<string>  $levelIds  معرّفات المستويات بالترتيب الجديد
     */
    public function execute(string $programId, array $levelIds): void
    {
        $this->assertAllBelongToProgram($programId, $levelIds);

        $ordering = [];

        $this->transaction->run(function () use ($levelIds, &$ordering): void {
            foreach (array_values($levelIds) as $index => $levelId) {
                Level::query()
                    ->whereKey($levelId)
                    ->update(['sort_order' => $index + 1]);

                $ordering[(string) $levelId] = $index + 1;
            }
        });

        $this->events->dispatch(new LevelsReordered(
            programId: $programId,
            ordering: $ordering,
        ));
    }

    /**
     * @param  list<string>  $levelIds
     */
    private function assertAllBelongToProgram(string $programId, array $levelIds): void
    {
        $ownedCount = Level::query()
            ->where('program_id', $programId)
            ->whereKey($levelIds)
            ->count();

        if ($ownedCount !== count(array_unique($levelIds))) {
            throw BusinessRuleViolation::make(
                'academics.level_not_in_program',
                'academics::errors.level_not_in_program',
                ['program_id' => $programId],
            );
        }
    }
}
