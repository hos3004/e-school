<?php

declare(strict_types=1);

namespace Modules\Reporting\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * رسالة شهرية واحدة لكل برنامج — تجميع تقارير الحصص المُرسَلة عن طلابه
 * خلال الفترة. لا تمر عبر Outbox إشعارات الطلاب — إرسال إداري مباشر
 * لمستلم واحد مضبوط سلفًا من إعدادات موديول Reporting.
 */
final class MonthlyProgramDigestMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * $mailLocale (لا $locale) لأن Mailable الأب يملك خاصية $locale غير readonly
     * بالفعل — إعلان خاصية readonly بنفس الاسم في الابن قاتل (fatal) في PHP.
     *
     * @param array<string, string> $programName
     * @param array<string, array{topics_covered: ?string, homework_assigned: ?string, participation: ?int, performance: ?int, commitment: ?int, note: ?string, submitted_at: string}[]> $entriesByStudentName
     */
    public function __construct(
        public readonly array $programName,
        public readonly string $periodFromLabel,
        public readonly string $periodToLabel,
        public readonly array $entriesByStudentName,
        public readonly string $mailLocale,
    ) {}

    public function build(): self
    {
        $names = $this->programName;
        $programLabel = $names[$this->mailLocale] ?? (reset($names) ?: '');

        return $this
            ->locale($this->mailLocale)
            ->subject((string) __('reporting::mail.monthly_program_digest.subject', [
                'program' => $programLabel,
                'from' => $this->periodFromLabel,
                'to' => $this->periodToLabel,
            ], $this->mailLocale))
            ->view('reporting::mail.monthly_program_digest')
            ->with([
                'programLabel' => $programLabel,
                'periodFromLabel' => $this->periodFromLabel,
                'periodToLabel' => $this->periodToLabel,
                'entriesByStudentName' => $this->entriesByStudentName,
            ]);
    }
}
