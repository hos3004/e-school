<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Console;

use Illuminate\Console\Command;
use Modules\Sessions\Application\Actions\SearchSubstituteCandidatesAction;
use Modules\Sessions\Domain\Models\TeacherApology;

final class SearchPendingSubstitutes extends Command
{
    protected $signature = 'sessions:search-substitutes {--limit=100}';

    protected $description = 'Refresh candidates for approved teacher apologies awaiting substitutes';

    public function handle(SearchSubstituteCandidatesAction $search): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $processed = 0;

        TeacherApology::query()
            ->awaitingSubstitute()
            ->whereHas('session', static fn ($query) => $query
                ->where('scheduled_end', '>', now('UTC')))
            ->orderByRaw('last_substitute_search_at NULLS FIRST')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (TeacherApology $apology) use ($search, &$processed): void {
                $search->execute($apology);
                $processed++;
            });

        $this->info((string) __('sessions::messages.substitute_search_summary', [
            'count' => $processed,
        ]));

        return self::SUCCESS;
    }
}
