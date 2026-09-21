<?php

declare(strict_types=1);

use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Academics\Domain\ValueObjects\AcademicCatalogItemData;
use Modules\Students\Application\Services\RegistrationNotificationDetails;

/**
 * دليل أكاديمي مزيّف: الاختبار يقيس تركيب اسم الكورس للرسالة لا قراءة الجدول.
 *
 * @param array<string, AcademicCatalogItemData> $courses
 */
function registrationCatalogStub(array $courses): AcademicCatalogQueries
{
    return new class($courses) implements AcademicCatalogQueries
    {
        /** @param array<string, AcademicCatalogItemData> $courses */
        public function __construct(private array $courses) {}

        public function programs(string $organizationId): array
        {
            return [];
        }

        public function courses(string $organizationId, string $programId): array
        {
            return [];
        }

        public function levels(string $organizationId, string $programId): array
        {
            return [];
        }

        public function programsByIds(string $organizationId, array $programIds): array
        {
            return [];
        }

        public function coursesByIds(string $organizationId, array $courseIds): array
        {
            return array_intersect_key($this->courses, array_flip($courseIds));
        }

        public function levelsByIds(string $organizationId, array $levelIds): array
        {
            return [];
        }

        public function closureFactsForProgram(string $organizationId, string $programId): array
        {
            return ['levels_total' => 0, 'courses_total' => 0, 'courses_open' => 0, 'courses_active' => 0, 'courses_closed' => 0];
        }

        public function courseIdsForProgram(string $organizationId, string $programId): array
        {
            return [];
        }

        public function closureFactsForCourse(string $organizationId, string $courseId): array
        {
            return ['level_id' => null, 'program_id' => null, 'planned_sessions' => null, 'is_active' => false];
        }
    };
}

it('uses the catalog name of the requested course in every supported locale', function (): void {
    $details = new RegistrationNotificationDetails(registrationCatalogStub([
        'course-1' => new AcademicCatalogItemData(
            id: 'course-1',
            code: 'QRN-1',
            name: ['ar' => 'تحفيظ القرآن', 'en' => 'Quran memorisation'],
        ),
    ]));

    expect($details->courseName('org-1', 'course-1'))
        ->toBe(['ar' => 'تحفيظ القرآن', 'en' => 'Quran memorisation']);
});

it('falls back to a translated placeholder instead of dropping the notification', function (): void {
    config(['notifications.localization.supported' => ['ar', 'en']]);
    $details = new RegistrationNotificationDetails(registrationCatalogStub([]));

    foreach ([null, '', '   ', 'missing-course'] as $courseId) {
        $name = $details->courseName('org-1', $courseId);

        expect($name)->toHaveKeys(['ar', 'en'])
            ->and($name['ar'])->toBe(trans('students::registration.notifications.course_unspecified', [], 'ar'))
            ->and($name['en'])->toBe(trans('students::registration.notifications.course_unspecified', [], 'en'))
            // مفتاح ترجمة غير معرّف يعود كما هو؛ نص الرسالة لا يحتمل ذلك.
            ->and($name['ar'])->not->toContain('students::')
            ->and($name['en'])->not->toContain('students::');
    }
});
