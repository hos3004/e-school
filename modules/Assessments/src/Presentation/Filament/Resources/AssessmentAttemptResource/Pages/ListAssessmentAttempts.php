<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Filament\Resources\AssessmentAttemptResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Assessments\Presentation\Filament\Resources\AssessmentAttemptResource;

/**
 * صفحة فهرس AssessmentAttemptResource — كان المورد مُعلنًا في اللوحة بلا صفحات، فلا مسار له ولا
 * سبيل لفتحه رغم ظهوره في الشيفرة.
 */
final class ListAssessmentAttempts extends ListRecords
{
    protected static string $resource = AssessmentAttemptResource::class;
}
