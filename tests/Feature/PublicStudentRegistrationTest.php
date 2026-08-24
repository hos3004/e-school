<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicStudentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_page_can_be_rendered(): void
    {
        $response = $this->get('/register/student');

        $response->assertStatus(200);
    }

    public function test_student_can_submit_public_registration(): void
    {
        $response = $this->post('/register/student', [
            'first_name' => 'أحمد',
            'last_name' => 'على',
            'email' => 'ahmed.public@example.com',
            'phone' => '+966500000000',
            'date_of_birth' => '2010-05-15',
            'gender' => 'male',
            'country' => 'SA',
            'city' => 'الرياض',
            'notes' => 'طالب مستجد',
        ]);

        $response->assertStatus(302);
        $response->assertRedirectContains('/register/submitted');

        $this->assertDatabaseHas('registration_applications', [
            'email' => 'ahmed.public@example.com',
            'status' => 'pending',
        ]);
    }

    public function test_application_status_page_can_be_rendered(): void
    {
        $response = $this->get('/register/status/non-existent-ulid');

        $response->assertStatus(200);
    }
}
