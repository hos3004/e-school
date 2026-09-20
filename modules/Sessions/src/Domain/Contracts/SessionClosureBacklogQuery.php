<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Contracts;

use Carbon\CarbonImmutable;
use Modules\Sessions\Domain\ValueObjects\SessionClosureBacklog;

/**
 * طابور الحصص التي مضى وقتها ولم يُبتّ فيها — عدًّا لا صفوفًا.
 *
 * هذا هو **التعريف الوحيد** لرقم «حصص بلا إقفال» في المنصة كلها. سبب وجوده أن
 * ستة قياسات مستقلة لنفس السؤال أعطت أربعة أرقام مختلفة (61 و56 و57 و61)،
 * والفرق لم يكن تحرّك بيانات بل اختلاف تعريف: هل تُضم `awaiting_review`؟ هل
 * هناك مهلة بعد انتهاء الموعد؟ هل تُستبعد `superseded`؟ وبطاقتان لرقم واحد
 * تختلفان بواحد تُسقطان مصداقية اللوحة في أول يوم.
 *
 * التعريف المثبَّت هنا: `scheduled` أو `confirmed`، ومضى على انتهاء موعدها أكثر
 * من مهلة الإعدادات، وليست محذوفة ولا `superseded`.
 *
 * `awaitingReview` صنف آخر بالكامل وله دالته: تلك حصص بلغت المراجعة فعلًا —
 * أي مرّت بـ`in_progress` — فهي تسرّبت من قُمع يعمل، بينما الطابور أعلاه لم
 * يدخل القُمع أصلًا. ضمّهما في عدّاد واحد هو بالضبط منشأ التضارب.
 *
 * يعيد عدّادات لا صفوفًا عمدًا: `OperationalReportQuery` يحمّل الصفوف كاملة
 * ويرفع `limitExceeded` عند التجاوز، فيصمت الرقم عند الحد بدل أن يكبر — وهو
 * أسوأ سلوك ممكن لعدّاد غرضه إظهار التراكم.
 */
interface SessionClosureBacklogQuery
{
    /**
     * لحظة القطع التي يُقاس عليها الطابور.
     *
     * تُحسب مرة واحدة ويمرّرها المستهلك إلى بقية الدوال، فلا تنزلق حصة بين
     * نداءين يحسب كلٌّ منهما `now()` بنفسه — وهو ما كان يجعل مجموع «فُتحت
     * غرفتها» و«لم تُفتح» لا يساوي المجموع المعروض فوقهما.
     */
    public function cutoff(): CarbonImmutable;

    /** الحصص التي مضى موعدها ولم يحرّكها شيء — لم تدخل المراجعة أصلًا. */
    public function backlog(string $organizationId, CarbonImmutable $cutoff): SessionClosureBacklog;

    /** الحصص التي بلغت «بانتظار المراجعة» وتنتظر قرارًا بشريًا. */
    public function awaitingReview(string $organizationId): SessionClosureBacklog;

    /**
     * معرّفات حصص الطابور، لمن يحتاج تقاطعًا مع عقد آخر (غرفة فُتحت، قابلية تسعير).
     *
     * @return list<string>
     */
    public function backlogSessionIds(string $organizationId, CarbonImmutable $cutoff): array;

    /**
     * معرّفات الحصص المنتظرة للمراجعة — صنف مستقل عن الطابور بمعرّفاته الخاصة.
     *
     * @return list<string>
     */
    public function awaitingReviewSessionIds(string $organizationId): array;

    /**
     * معرّفات الحصص التي مضى موعدها داخل نافذة زمنية، أيًّا كانت حالتها النهائية.
     *
     * يحتاجها من يقيس اكتمال الدفتر: الحصة المنتهية بحالة نهائية (غياب، إلغاء،
     * تأجيل) قد تحمل أثرًا ماليًا ولا تعود أبدًا إلى الطابور ولا إلى المراجعة —
     * فقياس «ما لم يدخل الدفتر» بالطابور وحده يُنتج مقامًا أصغر من الحقيقة،
     * ويصل إلى صفر بينما حصص مالية خارج الدفتر فعلًا.
     *
     * تُستبعد `superseded` والمحذوفة: أثر إعادة جدولة لا عمل.
     *
     * @return list<string>
     */
    public function pastSessionIdsInWindow(
        string $organizationId,
        CarbonImmutable $fromUtc,
        CarbonImmutable $untilUtcExclusive,
    ): array;

    /**
     * عدد الحصص القائمة فعلًا في نافذة زمنية — للعرض لا للمال.
     *
     * تُستبعد `superseded` والملغاة: الأولى أثر إعادة جدولة، والثانية لن يحضرها
     * أحد، وعدّهما في «حصص اليوم» يضخّم رقمًا يقرأه المالك على أنه عبء يومه.
     * يُعدّ في قاعدة البيانات ولا يحمّل صفوفًا: العدّاد يجب أن يكبر مع التراكم
     * لا أن يصمت عند حد صفحة.
     */
    public function countActiveBetween(
        string $organizationId,
        CarbonImmutable $fromUtc,
        CarbonImmutable $untilUtcExclusive,
    ): int;
}
