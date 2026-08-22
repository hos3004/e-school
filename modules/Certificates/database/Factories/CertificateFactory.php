<?php

declare(strict_types=1);

namespace Modules\Certificates\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Certificates\Domain\Models\Certificate;
use Modules\Certificates\Domain\Models\CertificateTemplate;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<Certificate>
 */
final class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    public function definition(): array
    {
        $issuedAt = CarbonImmutable::now('UTC')->subDays($this->faker->numberBetween(1, 120));

        return [
            'organization_id' => Fixtures::organizationId(),
            'certificate_template_id' => null,
            'student_profile_id' => Fixtures::studentProfileId(),
            'program_id' => null,
            'enrollment_id' => null,
            'serial_number' => 'CERT-'.$issuedAt->format('Y').'-'.$this->faker->unique()->regexify('[A-Z0-9]{10}'),
            'title' => [
                'ar' => 'شهادة إتمام '.$this->faker->word(),
                'en' => 'Completion certificate '.$this->faker->word(),
            ],
            'issued_at' => $issuedAt,
            'issued_by' => Fixtures::userId(),
            'expires_at' => $issuedAt->addYears(2),
            'metadata' => null,
        ];
    }

    public function withTemplate(?CertificateTemplate $template = null): static
    {
        return $this->state(fn (): array => [
            'certificate_template_id' => ($template ?? CertificateTemplate::factory()->create())->getKey(),
        ]);
    }

    public function withoutExpiry(): static
    {
        return $this->state(fn (): array => ['expires_at' => null]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'issued_at' => CarbonImmutable::now('UTC')->subYears(3),
            'expires_at' => CarbonImmutable::now('UTC')->subYear(),
        ]);
    }
}
