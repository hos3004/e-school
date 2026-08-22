<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\Contracts;

use Illuminate\Http\Request;
use Modules\VirtualClassroom\Domain\ValueObjects\ClassroomHealth;
use Modules\VirtualClassroom\Domain\ValueObjects\ClassroomSpec;
use Modules\VirtualClassroom\Domain\ValueObjects\JoinRequest;
use Modules\VirtualClassroom\Domain\ValueObjects\ParticipantSnapshot;
use Modules\VirtualClassroom\Domain\ValueObjects\RecordingHandle;
use Modules\VirtualClassroom\Domain\ValueObjects\RemoteClassroom;
use Modules\VirtualClassroom\Domain\ValueObjects\WebhookEvent;

/**
 * العقد الوحيد بين المنصة وأي مزوّد فصل مباشر.
 *
 * لماذا هذا العقد موجود:
 *   الطالب يضغط "دخول الحصة" ولا يعرف — ولا يحتاج أن يعرف — من يشغّل الفصل.
 *   لو قررنا بعد ستة أشهر تبديل BigBlueButton بغيره، نكتب Adapter جديدًا
 *   ولا نلمس الجدولة ولا الحضور ولا التسجيلات ولا الواجهات.
 *
 * قواعد على كل تنفيذ:
 *   - لا يُسرّب أي نوع خاص بالمزوّد خارج هذا الـ namespace.
 *   - كل خطأ من المزوّد يُترجم إلى ClassroomException بسبب مفهوم.
 *   - كل العمليات idempotent: استدعاء create مرتين لنفس الحصة لا ينشئ فصلين.
 *
 * التنفيذ الحالي: BigBlueButtonProvider. انظر docs/11-provider-interfaces.md
 */
interface VirtualClassroomProvider
{
    /**
     * الاسم المستقر للمزوّد كما في config/virtual-classroom.php
     */
    public function name(): string;

    /**
     * إنشاء الفصل عند المزوّد (أو إرجاع القائم إن وُجد).
     *
     * يُستدعى قبل الموعد بوقت كافٍ وليس عند ضغط الطالب على الدخول،
     * حتى لا يتحول بطء المزوّد إلى تأخير في بداية الحصة.
     */
    public function createClassroom(ClassroomSpec $spec): RemoteClassroom;

    /**
     * رابط دخول موقّع وقصير العمر لمشارك بعينه.
     *
     * الرابط شخصي: يحمل هوية المشارك ودوره (moderator / viewer)،
     * ولا يجوز مشاركته أو تخزينه أو إرساله في إشعار.
     */
    public function generateJoinUrl(JoinRequest $request): string;

    /**
     * هل الفصل مفتوح فعلًا عند المزوّد الآن؟
     */
    public function isRunning(string $externalId): bool;

    /**
     * لقطة بالمشاركين الحاليين — تُستخدم لحساب الحضور آليًا.
     *
     * @return list<ParticipantSnapshot>
     */
    public function participants(string $externalId): array;

    /**
     * إنهاء الفصل وطرد كل المشاركين.
     */
    public function endClassroom(string $externalId): void;

    /**
     * بدء أو استئناف التسجيل أثناء الحصة.
     */
    public function startRecording(string $externalId): void;

    public function pauseRecording(string $externalId): void;

    /**
     * التسجيلات الجاهزة لحصة — قد تكون فارغة لأن المعالجة تستغرق وقتًا.
     *
     * @return list<RecordingHandle>
     */
    public function recordings(string $externalId): array;

    /**
     * حذف تسجيل نهائيًا من المزوّد.
     *
     * لا يُستدعى إلا بعد نجاح الأرشفة أو بقرار حذف معتمد،
     * ويُسجَّل دائمًا في سجل التدقيق.
     */
    public function deleteRecording(string $recordingId): void;

    /**
     * تحويل webhook خام من المزوّد إلى حدث موحّد تفهمه المنصة.
     *
     * يجب أن يتحقق التنفيذ من صحة التوقيع قبل أي شيء آخر،
     * ويُرجع null لو كان الحدث غير معروف أو غير مهم.
     */
    public function parseWebhook(Request $request): ?WebhookEvent;

    /**
     * فحص صحة الخدمة — يُستدعى دوريًا وقبل بدء موجة الحصص.
     */
    public function healthCheck(): ClassroomHealth;

    /**
     * قدرات المزوّد، حتى تعرف الواجهة ما تعرضه وما تخفيه.
     *
     * @return array<string, bool>
     */
    public function capabilities(): array;
}
