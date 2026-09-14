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

    /**
     * أزواج التأجيل: الحصة المؤجَّلة ومعرّف حصتها التعويضية.
     *
     * يحتاجها أمر معالجة التأجيلات الفائتة: معرّف التعويضية لا يحمله
     * `SessionPayrollFacts` للأصلية، بل يحمله حدث التأجيل وحده، وهو ما فُقد
     * في التأجيلات التي مرّت قبل توحيد نقطة الإطلاق.
     *
     * @return list<array{original_session_id: string, makeup_session_id: string, organization_id: string, course_id: string, staff_profile_id: string, makeup_start: string, makeup_end: string}>
     */
    public function postponedPairs(int $limit, ?string $afterOriginalSessionId = null): array;
}
