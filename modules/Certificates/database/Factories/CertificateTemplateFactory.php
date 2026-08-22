<?php

declare(strict_types=1);

namespace Modules\Certificates\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Certificates\Domain\Models\CertificateTemplate;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<CertificateTemplate>
 */
final class CertificateTemplateFactory extends Factory
{
    protected $model = CertificateTemplate::class;

    public function definition(): array
    {
        $word = $this->faker->unique()->word();

        return [
            'organization_id' => Fixtures::organizationId(),
            'program_id' => null,
            'name' => [
                'ar' => 'قالب شهادة '.$word,
                'en' => 'Certificate template '.$word,
            ],
            'layout' => [
                'orientation' => 'landscape',
                'accent' => $this->faker->hexColor(),
            ],
            'background_image_path' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function forProgram(string $programId): static
    {
        return $this->state(fn (): array => ['program_id' => $programId]);
    }

    public function withBackground(): static
    {
        return $this->state(fn (): array => [
            'background_image_path' => 'templates/backgrounds/'.Str::ulid().'.png',
        ]);
    }
}
