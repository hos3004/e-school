<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Listeners;

use Modules\Sessions\Application\Actions\SearchSubstituteCandidatesAction;
use Modules\Sessions\Domain\Events\TeacherApologyDecided;
use Modules\Sessions\Domain\Models\TeacherApology;

final readonly class StartAutomaticSubstituteSearch
{
    public function __construct(private SearchSubstituteCandidatesAction $search) {}

    public function handle(TeacherApologyDecided $event): void
    {
        if ($event->decision !== 'approved' || !$event->substituteRequired) {
            return;
        }

        $apology = TeacherApology::query()
            ->whereKey($event->apologyId)
            ->where('organization_id', $event->organizationId)
            ->first();

        if (!$apology instanceof TeacherApology) {
            return;
        }

        $this->search->execute($apology);
    }
}
