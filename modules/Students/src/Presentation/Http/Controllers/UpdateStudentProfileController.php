<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Students\Application\Actions\UpdateStudentProfileAction;
use Modules\Students\Domain\Models\StudentProfile;
use Modules\Students\Presentation\Http\Requests\UpdateStudentProfileRequest;
use Modules\Students\Presentation\Http\Resources\StudentProfileResource;

/**
 * تحديث بيانات ملف طالب.
 */
final class UpdateStudentProfileController extends Controller
{
    public function __construct(
        private readonly UpdateStudentProfileAction $action,
    ) {}

    public function __invoke(UpdateStudentProfileRequest $request, StudentProfile $student): StudentProfileResource
    {
        $student = $this->action->execute($student, $request->validated());

        return StudentProfileResource::make($student);
    }
}
