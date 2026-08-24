<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Domain\Contracts\PhonePasswordResetOtpDelivery;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Tests\Concerns\CreatesTestOrganization;
use Modules\Identity\Tests\Support\FakePhonePasswordResetOtpDelivery;
use Tests\TestCase;

final class PhonePasswordResetEndpointTest extends TestCase
{
    use CreatesTestOrganization;
    use RefreshDatabase;

    private FakePhonePasswordResetOtpDelivery $delivery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestOrganization();
        $this->delivery = new FakePhonePasswordResetOtpDelivery;
        $this->app->instance(PhonePasswordResetOtpDelivery::class, $this->delivery);
    }

    public function test_forgot_response_is_uniform_for_known_and_unknown_accounts(): void
    {
        User::factory()->inOrganization($this->organizationId)->create(['phone' => '+201000000099']);

        $known = $this->postJson(route('identity.password.phone.request'), [
            'organization_id' => $this->organizationId,
            'phone' => '+201000000099',
        ])->assertOk()->json('message');

        $unknown = $this->postJson(route('identity.password.phone.request'), [
            'organization_id' => $this->organizationId,
            'phone' => '+201999999999',
        ])->assertOk()->json('message');

        self::assertSame($known, $unknown);
    }

    public function test_delivery_failure_keeps_the_public_forgot_response_uniform(): void
    {
        User::factory()->inOrganization($this->organizationId)->create(['phone' => '+201000000097']);
        $this->delivery->shouldFail = true;

        $this->postJson(route('identity.password.phone.request'), [
            'organization_id' => $this->organizationId,
            'phone' => '+201000000097',
        ])->assertOk()->assertJsonStructure(['message']);
    }

    public function test_full_http_reset_uses_delivered_otp_and_rejects_replay(): void
    {
        $user = User::factory()->inOrganization($this->organizationId)->create(['phone' => '+201000000098']);

        $this->postJson(route('identity.password.phone.request'), [
            'organization_id' => $this->organizationId,
            'phone' => '+201000000098',
        ])->assertOk();

        $otp = $this->delivery->deliveries[0]['otp'];
        $payload = [
            'organization_id' => $this->organizationId,
            'phone' => '+201000000098',
            'otp' => $otp,
            'password' => 'Unique-Endpoint-Password-2026',
            'password_confirmation' => 'Unique-Endpoint-Password-2026',
        ];

        $this->postJson(route('identity.password.phone.reset'), $payload)->assertOk();
        self::assertTrue(Hash::check('Unique-Endpoint-Password-2026', (string) $user->fresh()?->password));

        $this->postJson(route('identity.password.phone.reset'), $payload)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'identity.phone_reset_invalid');
    }

    public function test_phone_reset_requires_tenant_e164_otp_and_confirmed_strong_password(): void
    {
        $this->postJson(route('identity.password.phone.reset'), [
            'organization_id' => 'bad',
            'phone' => '01000000000',
            'otp' => '12ab',
            'password' => 'short',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['organization_id', 'phone', 'otp', 'password']);
    }
}
