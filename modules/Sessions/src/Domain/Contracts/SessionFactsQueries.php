<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Contracts;

use Modules\Sessions\Domain\ValueObjects\SessionPayrollFacts;

/**
 * العقد العام لقراءة حقائق الحصة من خارج موديول Sessions.
 *
 * أحداث المجال تحمل معرّفات فقط، وبعض المستهلكين — مثل دفتر المستحقات —
 * يحتاجون وقت الحصة ونوعها والمعلم الأصلي مقابل المنفّذ. هذا العقد هو
 * القناة المعلنة لذلك؛ لا يقرأ أحد جدول `sessions` مباشرة.
 */
interface SessionFactsQueries
{
    /**
     * حقائق حصة واحدة، أو null إن لم توجد أو كانت محذوفة.
     */
    public function payrollFactsFor(string $sessionId): ?SessionPayrollFacts;
}
