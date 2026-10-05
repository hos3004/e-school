<?php

declare(strict_types=1);

namespace Modules\Students\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Students\Database\Seeders\RegistrationFormsSeeder;
use Modules\Students\Domain\Models\RegistrationForm;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class RegistrationFormsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_three_reference_forms_with_their_questions(): void
    {
        $organizationId = Fixtures::organizationId();

        $this->seed(RegistrationFormsSeeder::class);

        $forms = RegistrationForm::query()
            ->forOrganization($organizationId)
            ->withCount('questions')
            ->orderBy('slug')
            ->get()
            ->keyBy('slug');

        $this->assertSame([
            'free-online-classes',
            'kids-coding-ai',
            'quran-sessions',
        ], $forms->keys()->all());

        foreach ($forms as $form) {
            $this->assertTrue($form->is_active);
            $this->assertSame(5, $form->questions_count);
        }
    }
}
