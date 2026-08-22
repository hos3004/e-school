<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Students\Domain\Models\StudentProfile;
use Modules\Students\Presentation\Http\Resources\StudentProfileResource;

/**
 * عرض ملف طالب واحد.
 */
final class ShowStudentProfileController extends Controller
{
    public function __invoke(StudentProfile $student): StudentProfileResource
    {
        abort_unless(request()->user()?->can('view', $student), 403);

        return StudentProfileResource::make($student);
    }
}
