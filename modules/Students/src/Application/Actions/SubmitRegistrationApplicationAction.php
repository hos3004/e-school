<?php

declare(strict_types=1);

namespace Modules\Students\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Events\RegistrationSubmitted;
use Modules\Students\Domain\Models\RegistrationApplication;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class SubmitRegistrationApplicationAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(RegistrationApplication $application): RegistrationApplication
    {
        $application = $this->transaction->run(function () use ($application): RegistrationApplication {
            /** @var RegistrationApplication $locked */
            $locked = RegistrationApplication::query()
                ->lockForUpdate()
                ->findOrFail($application->getKey());

            if (!$locked->status->canTransitionTo(RegistrationStatus::Submitted)) {
                throw BusinessRuleViolation::make(
                    'registration.invalid_transition',
                    'students::errors.registration_invalid_transition',
                    ['from' => $locked->status->label(), 'to' => RegistrationStatus::Submitted->label()],
                );
            }

            $this->assertRequiredFieldsPresent($locked);
            $duplicateId = $this->findDuplicateApplicationId($locked);

            if ($duplicateId !== null && config('admission.self_registration.duplicate_detection.block_or_flag') === 'block') {
                throw BusinessRuleViolation::make(
                    'registration.duplicate_blocked',
                    'students::errors.registration_duplicate_blocked',
                );
            }

            $locked->status = RegistrationStatus::Submitted;
            $locked->submitted_at = now()->utc();
            $locked->duplicate_of_application_id = $duplicateId;
            $locked->save();

            return $locked;
        });

        $this->events->dispatch(new RegistrationSubmitted(
            applicationId: (string) $application->id,
            organizationId: (string) $application->organization_id,
            fullName: $application->full_name,
            studentUserId: $application->user_id,
        ));

        return $application;
    }

    private function assertRequiredFieldsPresent(RegistrationApplication $application): void
    {
        /** @var list<string> $requiredFields */
        $requiredFields = array_values((array) config('admission.self_registration.required_fields', []));

        foreach ($requiredFields as $field) {
            if ($field === 'contact') {
                if ($this->hasValue($application->email) || $this->hasValue($application->phone)) {
                    continue;
                }

                throw BusinessRuleViolation::make(
                    'registration.contact_required',
                    'students::errors.registration_contact_required',
                );
            }

            if (!$this->hasValue($application->getAttribute($field))) {
                throw BusinessRuleViolation::make(
                    'registration.required_field_missing',
                    'students::errors.registration_required_field_missing',
                    ['field' => __('students::attributes.'.$field)],
                );
            }
        }
    }

    private function hasValue(mixed $value): bool
    {
        return $value !== null && (!is_string($value) || trim($value) !== '');
    }

    private function findDuplicateApplicationId(RegistrationApplication $application): ?string
    {
        if (!(bool) config('admission.self_registration.duplicate_detection.enabled', true)) {
            return null;
        }

        $allowedFields = ['email', 'phone'];
        $configuredFields = array_values((array) config('admission.self_registration.duplicate_detection.match_on', []));
        $matchValues = [];

        foreach (array_intersect($allowedFields, $configuredFields) as $field) {
            $value = $application->getAttribute($field);
            if ($this->hasValue($value)) {
                $matchValues[$field] = $value;
            }
        }

        if ($matchValues === []) {
            return null;
        }

        $duplicateId = RegistrationApplication::query()
            ->withTrashed()
            ->where('organization_id', $application->organization_id)
            ->whereKeyNot($application->getKey())
            ->where(function (Builder $query) use ($matchValues): void {
                foreach ($matchValues as $field => $value) {
                    $query->orWhere($field, $value);
                }
            })
            ->oldest('created_at')
            ->value('id');

        return is_string($duplicateId) ? $duplicateId : null;
    }
}
