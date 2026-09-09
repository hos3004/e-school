<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\SaveSessionPayRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Organization\Application\Actions\UpsertOrganizationSetting;
use Modules\Organization\Domain\Contracts\OrganizationSettingQueries;
use Modules\Organization\Domain\Models\Organization;
use Modules\Staff\Domain\Contracts\SessionPayCatalog;

final class SessionPayController extends Controller
{
    /** @return array<string, mixed> */
    public static function data(string $organizationId): array
    {
        $history = app(OrganizationSettingQueries::class)->value($organizationId, (string) config('session_pay.setting_key'));
        $rates = app(SessionPayCatalog::class)->rates($organizationId);
        if ($history === null) {
            $rates = (array) config('session_pay.defaults');
        }

        return ['version' => ConsoleSettingsData::fingerprint(['history' => $history]), 'currency' => config('session_pay.currency'),
            'rates' => array_map(static fn (array $rate): array => [
                'name' => $rate['name'], 'session_type' => $rate['session_type'], 'duration_minutes' => $rate['duration_minutes'],
                'price' => number_format($rate['amount'] / 100, 2, '.', ''),
            ], $rates)];
    }

    public function store(SaveSessionPayRequest $request, AuditRecorder $audit): RedirectResponse
    {
        $data = $request->validated();
        $organizationId = (string) data_get($request->user(), 'organization_id');
        DB::transaction(function () use ($request, $data, $organizationId, $audit): void {
            $organization = Organization::query()->lockForUpdate()->findOrFail($organizationId);
            Gate::authorize('manageSettings', $organization);
            $key = (string) config('session_pay.setting_key');
            $history = app(OrganizationSettingQueries::class)->value($organizationId, $key);
            if (!hash_equals(self::data($organizationId)['version'], $data['version'])) {
                throw ValidationException::withMessages(['version' => __('console.settings.concurrent')]);
            }
            $rates = array_map(static function (array $row): array {
                $parts = explode('.', (string) $row['price']);

                return ['id' => (string) Str::ulid(), 'name' => $row['name'], 'session_type' => $row['session_type'], 'duration_minutes' => (int) $row['duration_minutes'],
                    'amount' => (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0'), 'currency' => (string) config('session_pay.currency')];
            }, $data['rates']);
            $revision = ['effective_from' => now('UTC')->format('Y-m-d\\TH:i:s.uP'), 'rates' => $rates];
            app(UpsertOrganizationSetting::class)->execute($organization, $key, [...(is_array($history) ? $history : []), $revision]);
            $audit->record($organizationId, (string) $request->user()?->getAuthIdentifier(), 'user', 'console.settings.session-pay', Organization::class, $organizationId,
                ['rates' => is_array($history) && $history !== [] ? end($history)['rates'] : []], $revision, $data['reason']);
        });

        return to_route('console.settings')->with('success', __('session_pay.saved'));
    }
}
