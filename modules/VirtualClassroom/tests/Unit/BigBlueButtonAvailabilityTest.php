<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\VirtualClassroom\Infrastructure\Providers\BigBlueButtonProvider;

function bbbAvailabilityProvider(): BigBlueButtonProvider
{
    return new BigBlueButtonProvider([
        'base_url' => 'https://bbb.test/bigbluebutton/',
        'secret' => 'api-secret',
        'timeout_seconds' => 10,
        'connect_timeout_seconds' => 5,
        'retry_delays_milliseconds' => [],
        'circuit_breaker' => ['failure_threshold' => 2, 'open_seconds' => 120],
        'supports' => [],
    ]);
}

beforeEach(function (): void {
    Cache::flush();
});

it('treats a created room nobody has entered yet as available', function (): void {
    // هذا ما يرد به BBB قبل أول دخول: running=false لكن الغرفة قائمة.
    Http::fake(['bbb.test/*' => Http::response(
        '<response><returncode>SUCCESS</returncode><running>false</running>'
        .'<hasBeenForciblyEnded>false</hasBeenForciblyEnded><endTime>0</endTime></response>',
    )]);

    expect(bbbAvailabilityProvider()->isAvailable('meeting-1'))->toBeTrue();
    Http::assertSent(static fn (ClientRequest $request): bool => str_contains($request->url(), '/getMeetingInfo?'));
});

it('treats a room the provider no longer knows as unavailable', function (): void {
    Http::fake(['bbb.test/*' => Http::response(
        '<response><returncode>FAILED</returncode><messageKey>notFound</messageKey></response>',
    )]);

    expect(bbbAvailabilityProvider()->isAvailable('meeting-1'))->toBeFalse();
});

it('treats a room that already ended as unavailable', function (): void {
    Http::fake(['bbb.test/*' => Http::response(
        '<response><returncode>SUCCESS</returncode><running>false</running>'
        .'<hasBeenForciblyEnded>false</hasBeenForciblyEnded><endTime>1789239033577</endTime></response>',
    )]);

    expect(bbbAvailabilityProvider()->isAvailable('meeting-1'))->toBeFalse();
});
