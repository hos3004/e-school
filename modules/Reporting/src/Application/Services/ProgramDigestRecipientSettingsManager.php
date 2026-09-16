<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Notifications\Domain\Contracts\EmailDeliverabilityCheck;
use Modules\Reporting\Domain\Contracts\ProgramDigestRecipientSettings;
use Modules\Reporting\Domain\Enums\DigestRecipientType;
use Modules\Reporting\Domain\Models\ProgramDigestRecipientSetting;
use Modules\Reporting\Domain\ValueObjects\ProgramDigestRecipientData;

final readonly class ProgramDigestRecipientSettingsManager implements ProgramDigestRecipientSettings
{
    public function __construct(
        private AuditRecorder $audit,
        private UserAccountDirectory $users,
        private EmailDeliverabilityCheck $deliverability,
    ) {}

    public function current(string $organizationId): ?ProgramDigestRecipientData
    {
        $setting = $this->row($organizationId);

        return $setting === null ? null : self::data($setting);
    }

    public function saveGlobal(
        string $organizationId,
        string $recipientType,
        ?string $recipientUserId,
        ?string $customEmail,
        string $actorId,
        string $reason,
        ?string $expectedVersion,
    ): ProgramDigestRecipientData {
        $type = DigestRecipientType::tryFrom($recipientType);
        if ($type === null) {
            throw ValidationException::withMessages([
                'recipient_type' => __('reporting::settings.digest_recipient.invalid_type'),
            ]);
        }

        if ($type === DigestRecipientType::StaffEmail) {
            if ($recipientUserId === null || $recipientUserId === '') {
                throw ValidationException::withMessages([
                    'recipient_user_id' => __('reporting::settings.digest_recipient.user_required'),
                ]);
            }

            $account = $this->users->find($organizationId, $recipientUserId);
            if ($account === null || !$this->deliverability->isDeliverable($account->email)) {
                throw ValidationException::withMessages([
                    'recipient_user_id' => __('reporting::settings.digest_recipient.user_email_invalid'),
                ]);
            }
            $customEmail = null;
        } else {
            if (!$this->deliverability->isDeliverable($customEmail)) {
                throw ValidationException::withMessages([
                    'custom_email' => __('reporting::settings.digest_recipient.custom_email_invalid'),
                ]);
            }
            $recipientUserId = null;
        }

        return DB::transaction(function () use (
            $organizationId, $type, $recipientUserId, $customEmail, $actorId, $reason, $expectedVersion,
        ): ProgramDigestRecipientData {
            $existing = ProgramDigestRecipientSetting::query()
                ->forOrganization($organizationId)
                ->global()
                ->lockForUpdate()
                ->first();

            $currentVersion = $existing === null ? null : self::version($existing);
            if ($expectedVersion !== null && $expectedVersion !== $currentVersion) {
                throw ValidationException::withMessages([
                    'version' => __('reporting::settings.digest_recipient.concurrent_change'),
                ]);
            }

            $before = $existing === null ? null : self::data($existing)->recipientType.':'.
                (string) ($existing->recipient_user_id ?? $existing->custom_email);

            if ($existing === null) {
                $existing = new ProgramDigestRecipientSetting([
                    'organization_id' => $organizationId,
                ]);
            }

            $existing->recipient_type = $type;
            $existing->recipient_user_id = $recipientUserId;
            $existing->custom_email = $customEmail;
            $existing->lock_version = (int) ($existing->lock_version ?? 0) + 1;
            $existing->save();

            $after = self::data($existing);

            $this->audit->record(
                $organizationId,
                $actorId,
                'user',
                'reporting.settings.program_digest_recipient',
                ProgramDigestRecipientSetting::class,
                (string) $existing->id,
                ['recipient' => $before],
                ['recipient' => $after->recipientType.':'.(string) ($after->recipientUserId ?? $after->customEmail)],
                $reason,
            );

            return $after;
        });
    }

    public function resolveEmail(string $organizationId): ?string
    {
        $setting = $this->row($organizationId);
        if ($setting === null) {
            return null;
        }

        if ($setting->recipient_type === DigestRecipientType::CustomEmail) {
            return $this->deliverability->isDeliverable($setting->custom_email) ? $setting->custom_email : null;
        }

        if ($setting->recipient_user_id === null) {
            return null;
        }

        $account = $this->users->find($organizationId, (string) $setting->recipient_user_id);

        return $account !== null && $this->deliverability->isDeliverable($account->email) ? $account->email : null;
    }

    private function row(string $organizationId): ?ProgramDigestRecipientSetting
    {
        return ProgramDigestRecipientSetting::query()
            ->forOrganization($organizationId)
            ->global()
            ->first();
    }

    private static function data(ProgramDigestRecipientSetting $setting): ProgramDigestRecipientData
    {
        return new ProgramDigestRecipientData(
            id: (string) $setting->id,
            organizationId: (string) $setting->organization_id,
            recipientType: $setting->recipient_type->value,
            recipientUserId: $setting->recipient_user_id,
            customEmail: $setting->custom_email,
            version: self::version($setting),
        );
    }

    /**
     * قفل تفاؤلي بعدّاد صحيح لا بـ updated_at: عمود التوقيت هنا بدقة ثانية
     * واحدة (timestampsTz الافتراضي)، فحفظان متتاليان في نفس الثانية كانا
     * سينتجان توقيعًا متطابقًا ويُسقطان فحص التزامن صامتًا.
     */
    private static function version(ProgramDigestRecipientSetting $setting): string
    {
        return (string) $setting->lock_version;
    }
}
