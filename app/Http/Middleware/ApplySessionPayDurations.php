<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Staff\Domain\Contracts\SessionPayCatalog;
use Symfony\Component\HttpFoundation\Response;

final class ApplySessionPayDurations
{
    public function handle(Request $request, Closure $next): Response
    {
        $org = (string) data_get($request->user(), 'organization_id', '');
        if ($org === '') {
            return $next($request);
        }
        $keys = ['scheduling.individual_session_durations', 'scheduling.session_durations', 'scheduling.default_individual_duration_minutes', 'scheduling.default_duration_minutes'];
        $original = [];
        foreach ($keys as $key) {
            $original[$key] = config($key);
        }
        $catalog = app(SessionPayCatalog::class);
        $individual = $catalog->durations($org, 'individual');
        $group = $catalog->durations($org, 'group');
        config([
            $keys[0] => $individual, $keys[1] => $group,
            $keys[2] => in_array($original[$keys[2]], $individual, true) ? $original[$keys[2]] : ($individual[0] ?? $original[$keys[2]]),
            $keys[3] => in_array($original[$keys[3]], $group, true) ? $original[$keys[3]] : ($group[0] ?? $original[$keys[3]]),
        ]);
        try {
            return $next($request);
        } finally {
            config($original);
        }
    }
}
