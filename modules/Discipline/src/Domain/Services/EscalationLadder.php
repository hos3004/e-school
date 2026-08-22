<?php

declare(strict_types=1);

namespace Modules\Discipline\Domain\Services;

use Modules\Discipline\Domain\Enums\DisciplineActionType;

/**
 * سُلَّم التصعيد — يقرأ config('discipline.ladder') ويحدد الإجراء المنطبق.
 *
 * القاعدة: أعلى عتبة بلغها العدّاد هي التي تُطبَّق. لا رقم عتبة داخل الكود أبدًا.
 *
 * @template TEntry of array{threshold: int, action: string, ...}
 */
final readonly class EscalationLadder
{
    /** @var list<array<string, mixed>> */
    private array $entries;

    /**
     * @param list<array<string, mixed>>|null $ladder للتجاوز في الاختبارات
     */
    public function __construct(?array $ladder = null)
    {
        /** @var list<array<string, mixed>> $configured */
        $configured = array_values((array) ($ladder ?? config('discipline.ladder', [])));

        usort($configured, fn (array $a, array $b): int => (int) $a['threshold'] <=> (int) $b['threshold']);

        $this->entries = $configured;
    }

    /**
     * الإجراء المطبَّق عند عدد مخالفات معيّن — null إن لم تُبلغ أي عتبة.
     *
     * @return array{action: DisciplineActionType, threshold_reached: int, automatic: bool}|null
     */
    public function resolveForCount(int $count): ?array
    {
        if ($count < 1) {
            return null;
        }

        $applied = null;

        foreach ($this->entries as $entry) {
            if ($count >= (int) $entry['threshold']) {
                $type = DisciplineActionType::fromLadderEntry($entry);

                if ($type !== null) {
                    $applied = [
                        'action' => $type,
                        'threshold_reached' => (int) $entry['threshold'],
                        'automatic' => (bool) ($entry['automatic'] ?? false),
                    ];
                }
            }
        }

        return $applied;
    }
}
