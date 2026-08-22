<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Organization\Application\Actions\RemoveHoliday;
use Modules\Organization\Domain\Models\Holiday;
use Modules\Organization\Presentation\Http\Requests\DeleteHolidayRequest;

final class DeleteHolidayController
{
    public function __construct(
        private readonly RemoveHoliday $removeHoliday,
    ) {}

    public function __invoke(DeleteHolidayRequest $request, Holiday $holiday): JsonResponse
    {
        $this->removeHoliday->execute($holiday);

        return response()->json(['deleted' => true]);
    }
}
