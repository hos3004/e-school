<?php

declare(strict_types=1);

namespace Modules\Students\Application\Actions;

use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Models\RegistrationApplication;
use Shared\Support\Transaction;

/** إنشاء المسودة فقط؛ لا ينشئ هذا المسار ملف طالب أو قيدًا أكاديميًا. */
final readonly class CreateRegistrationApplicationAction
{
    public function __construct(private Transaction $transaction) {}

    /**
     * @param array<string, mixed> $data
     */
    public function execute(array $data, string $organizationId, string $userId): RegistrationApplication
    {
        return $this->transaction->run(function () use ($data, $organizationId, $userId): RegistrationApplication {
            $application = new RegistrationApplication;
            $application->fill($data);
            $application->organization_id = $organizationId;
            $application->user_id = $userId;
            $application->status = RegistrationStatus::Draft;
            $application->save();

            return $application;
        });
    }
}
