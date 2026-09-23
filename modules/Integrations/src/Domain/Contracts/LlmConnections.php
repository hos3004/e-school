<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\Contracts;

/**
 * اتصال المؤسسة بمزوّد النموذج اللغوي: المفتاح، الحالة، ومفتاح الإيقاف.
 *
 * نفس عقد Green API عمدًا، لأن الدرس المستفاد هناك ينطبق حرفيًا هنا: الإيقاف
 * يجب أن يعمل دون إعادة إدخال بيانات الاعتماد، وأن يُفحَص عند كل نداء لا مرة
 * واحدة عند فتح المحادثة.
 */
interface LlmConnections
{
    /**
     * ملخص آمن للعرض في اللوحة — لا يحتوي المفتاح نفسه أبدًا، بل configured فقط.
     *
     * @return array<string, mixed>
     */
    public function view(string $organizationId): array;

    /**
     * بيانات الاعتماد الفعلية، أو null إذا لم يكن الاتصال مُفعَّلًا.
     *
     * الاتصال الموقوف يعيد null عمدًا حتى لا يتسلل مفتاح من البيئة ويشتغل
     * البوت بعد أن أوقفه الأدمن.
     *
     * @return array{api_key: non-empty-string, base_url: non-empty-string}|null
     */
    public function credentials(string $organizationId): ?array;

    /** هل البوت مسموح له بمناداة المزوّد الآن؟ يُفحَص قبل كل نداء. */
    public function isEnabled(string $organizationId): bool;

    /**
     * حفظ المفتاح. المفتاح لا يمر عبر شاشة يفتحها موظف: يُدخَل بسكربت على
     * الخادم كما يفعل scripts/enable-whatsapp.sh، والقيمة تُخزَّن مشفّرة.
     */
    public function save(string $organizationId, string $apiKey, string $baseUrl, string $actorId, string $reason): void;

    /**
     * التحقق من صلاحية المفتاح لدى المزوّد قبل التفعيل.
     *
     * يعيد null عند النجاح، أو سبب الفشل كنص عند الرفض.
     */
    public function verify(string $apiKey, string $baseUrl): ?string;

    /**
     * إيقاف/تشغيل فوري دون إعادة إدخال المفتاح. يعيد true إن تغيّرت الحالة.
     */
    public function setActive(string $organizationId, bool $active, string $actorId, string $reason): bool;

    /** تسجيل فشل المزوّد لعرضه في اللوحة دون تعطيل الاتصال. */
    public function recordState(string $organizationId, string $state): void;
}
