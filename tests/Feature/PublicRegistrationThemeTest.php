<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Modules\Students\Domain\Models\RegistrationForm;
use Modules\Students\Domain\Models\RegistrationQuestion;
use Tests\TestCase;

final class PublicRegistrationThemeTest extends TestCase
{
    use CreatesSessionParticipant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['console.enabled' => true, 'admission.self_registration.enabled' => true]);
    }

    public function test_console_theme_exposes_real_course_summary_and_keeps_every_published_question_type_in_arabic(): void
    {
        [$form] = $this->form();
        foreach (['text', 'textarea', 'select', 'radio', 'checkbox', 'number'] as $index => $type) {
            RegistrationQuestion::query()->create([
                'organization_id' => $form->organization_id, 'registration_form_id' => $form->id,
                'question' => ['ar' => 'سؤال التسجيل '.($index + 1), 'en' => 'Legacy question'],
                'type' => $type, 'options' => in_array($type, ['select', 'radio', 'checkbox'], true) ? ['الخيار الأول', 'الخيار الثاني'] : null,
                'is_required' => true, 'is_active' => true, 'sort_order' => $index,
            ]);
        }
        $this->withSession(['locale' => 'en'])->get('/register/student/'.$form->slug)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/RegisterStudent')
                ->where('consoleRegistration', true)->where('locale', 'ar')->where('direction', 'rtl')
                ->where('registrationForm.course', 'مادة اختبار')->where('registrationForm.program', 'برنامج اختبار')
                ->has('questions', 6)->where('questions.0.type', 'text')->where('questions.5.type', 'number')
                ->where('questions.0.required', true)->where('questions.4.options.1', 'الخيار الثاني')
                ->where('translations', fn ($translations): bool => ($translations['public_registration.brand'] ?? null) === 'تلي أكاديمي'));
        $form->update(['is_active' => false]);
        $this->get('/register/student/'.$form->slug)->assertNotFound();
    }

    public function test_course_summary_does_not_disclose_another_organization_and_legacy_layout_remains_available(): void
    {
        [$mine] = $this->form();
        [$foreignForm, $foreignSession] = $this->form();
        $mine->update(['preferred_course_id' => $foreignSession['course_id'], 'preferred_program_id' => $foreignForm->preferred_program_id]);
        $this->get('/register/student/'.$mine->slug)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('registrationForm.course', null)->where('registrationForm.program', null));
        config(['console.enabled' => false]);
        $this->get('/register/student/'.$mine->slug)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('consoleRegistration', false));
    }

    public function test_submitted_and_status_pages_use_same_theme_and_invalid_query_does_not_break_confirmation(): void
    {
        $this->withSession(['locale' => 'fr'])->get('/register/submitted?id[]=bad')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/RegistrationSubmitted')
                ->where('applicationId', null)->where('consoleRegistration', true)->where('locale', 'ar'));
        $this->get('/register/status/non-existent-ulid')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/ApplicationStatus')
                ->where('application', null)->where('consoleRegistration', true));
    }

    /** @return array{RegistrationForm,array<string,mixed>} */
    private function form(): array
    {
        $participant = DB::table('session_participants')->find($this->createSessionParticipant());
        $session = (array) DB::table('sessions')->find($participant->session_id);
        $programId = DB::table('levels')->where('id', DB::table('courses')->where('id', $session['course_id'])->value('level_id'))->value('program_id');
        $form = RegistrationForm::query()->create([
            'organization_id' => $session['organization_id'], 'slug' => 'public-theme-'.strtolower($session['id']),
            'title' => ['ar' => 'التسجيل في دورة التجويد', 'en' => 'Old registration'],
            'description' => ['ar' => 'أكمل البيانات للتقديم'], 'preferred_course_id' => $session['course_id'], 'preferred_program_id' => $programId, 'is_active' => true,
        ]);

        return [$form, $session];
    }
}
