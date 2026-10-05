<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\Request;

final readonly class LearningDestination
{
    public function __construct(private PortalData $data) {}

    /** @return array<string, string> */
    public function portals(Request $request): array
    {
        $user = $request->user();
        if ($user === null) {
            return [];
        }
        $org = (string) $user->getAttribute('organization_id');
        $id = (string) $user->getAuthIdentifier();
        $portals = [];
        if ($user->can('session.view') && $user->can('attendance.record') && $this->data->staffProfileId($id, $org) !== null) {
            $portals['teacher'] = route('learning.teacher.dashboard');
        }
        if ($user->can('session.view') && $this->data->studentProfileId($id, $org) !== null) {
            $portals['student'] = route('learning.student.dashboard');
        }
        if ($user->can('admin.panel.access')) {
            $portals['admin'] = route('console.home');
        }

        return $portals;
    }

    public function forRequest(Request $request): ?string
    {
        $portals = $this->portals($request);
        $preferred = $request->session()->pull('learning.portal', $request->input('portal'));

        return (is_string($preferred) ? ($portals[$preferred] ?? null) : null) ?? array_values($portals)[0] ?? null;
    }
}
