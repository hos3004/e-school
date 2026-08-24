# 11 — واجهات المزوّدين الخارجيين

> **قرار المرحلة الأولى:** BigBlueButton هو مزوّد الفصل الوحيد المطلوب، وZoom خارج
> النطاق. تبقى الواجهة العامة لحماية المعمارية، لا لفرض تنفيذ مزوّد ثانٍ.

كل خدمة خارجية تدخل المشروع من باب واحد: **واجهة نملكها نحن**.
لا مكتبة طرف ثالث تُستدعى مباشرة من منطق المجال، ولا نوع خاص بمزوّد
يعبر حدود موديول `Integrations` أو `VirtualClassroom`.

---

## 1. لماذا هذا الحاجز

| بدونه | معه |
|-------|-----|
| تبديل المزوّد = إعادة كتابة نصف المشروع | تبديل المزوّد = Adapter جديد |
| الاختبارات تحتاج شبكة | تنفيذ `Null` يعمل بلا شبكة |
| عطل المزوّد يظهر كخطأ تقني غامض | يُترجم لاستثناء مجال مفهوم |
| كل موديول يخترع طريقته في إعادة المحاولة | سلوك موحّد في مكان واحد |

---

## 2. القواعد الإلزامية لكل مزوّد

1. **الواجهة في `Domain/Contracts/`** والتنفيذات في `Infrastructure/Providers/`.
2. **تنفيذ `Null`** لكل واجهة — يُستخدم في الاختبارات وفي البيئات المحلية.
3. **مهلة زمنية صريحة** على كل استدعاء شبكي. لا استدعاء بلا timeout.
4. **إعادة محاولة بتراجع أسي** للأخطاء العابرة فقط — لا للأخطاء المنطقية.
5. **قاطع دائرة**: بعد 5 إخفاقات متتالية يُفتح لدقيقتين ويُرسل تنبيه.
6. **ترجمة الأخطاء**: كل استثناء من المكتبة يُلتقط ويُعاد رميه كاستثناء مجال.
7. **الأسرار من `.env`** — لا قيمة مكتوبة في الكود ولا في الاختبارات.
8. **`healthCheck()`** منفَّذ ومربوط بلوحة حالة النظام.
9. **idempotent**: تكرار نفس الاستدعاء لا ينتج أثرًا مضاعفًا.
10. **تسجيل**: كل استدعاء يُسجَّل بـ `correlation_id` والمدة، بلا أسرار.

---

## 3. الفصل المباشر — `VirtualClassroomProvider`

**الموديول:** `VirtualClassroom`
**الواجهة:** [`Domain/Contracts/VirtualClassroomProvider.php`](../modules/VirtualClassroom/src/Domain/Contracts/VirtualClassroomProvider.php) — **مُسلَّمة**
**التنفيذ الحالي:** `BigBlueButtonProvider`

### العمليات

```php
name(): string
createClassroom(ClassroomSpec): RemoteClassroom
generateJoinUrl(JoinRequest): string
isRunning(string $externalId): bool
participants(string $externalId): array
endClassroom(string $externalId): void
startRecording(string $externalId): void
pauseRecording(string $externalId): void
recordings(string $externalId): array
deleteRecording(string $recordingId): void
parseWebhook(Request): ?WebhookEvent
healthCheck(): ClassroomHealth
capabilities(): array
```

### كائنات القيمة

| الكائن | يحمل |
|--------|------|
| `ClassroomSpec` | sessionId · title · maxParticipants · autoRecord · welcomeMessage · locale · duration |
| `RemoteClassroom` | externalId · providerMeta · createdAt |
| `JoinRequest` | externalId · userId · displayName · role (moderator/viewer) · locale · avatarUrl |
| `ParticipantSnapshot` | externalUserId · displayName · role · joinedAt · isPresenter |
| `RecordingHandle` | externalRecordingId · status · durationSeconds · sizeBytes · downloadUrl · thumbnailUrl |
| `WebhookEvent` | type · sessionId · externalId · occurredAt · payload |
| `ClassroomHealth` | isHealthy · activeMeetings · activeParticipants · capacityPercent · message |

### قواعد خاصة

- **`createClassroom` تُستدعى قبل الموعد بـ 20 دقيقة**، لا عند ضغط الطالب.
  بطء المزوّد يجب ألا يتحول إلى تأخير في بدء الحصة.
- **`generateJoinUrl` تُنتج رابطًا شخصيًا قصير العمر.** لا يُخزَّن في قاعدة
  البيانات، ولا يُرسل في إشعار، ولا يُسجَّل في السجلات.
- **`parseWebhook` تتحقق من التوقيع أولًا** وتُرجع `null` للأحداث غير المعروفة.
- **`deleteRecording` لا تُستدعى** إلا بعد نجاح الأرشفة أو بقرار حذف معتمد،
  وتترك سطرًا في سجل التدقيق دائمًا.

### مصفوفة القدرات

| القدرة | BBB | Zoom SDK | Whereby | LiveKit |
|--------|:---:|:--------:|:-------:|:-------:|
| سبورة مشتركة | ✅ | ❌ | ❌ | ❌ |
| غرف فرعية | ✅ | ✅ | ✅ | يُبنى |
| استطلاعات | ✅ | ✅ | ❌ | يُبنى |
| رفع ملفات للعرض | ✅ | ❌ | ❌ | ❌ |
| ملاحظات مشتركة | ✅ | ❌ | ❌ | ❌ |
| تسجيل | ✅ | إضافي | ✅ | ✅ |
| تحكم المعلم | ✅ | ✅ | جزئي | يُبنى |

الواجهة تستعلم `capabilities()` وتُخفي ما لا يدعمه المزوّد الحالي —
بدل أن تُعطِّل زرًا لا يعمل.

---

## 4. الرسائل والإشعارات

**الموديول:** `Integrations`

### `WhatsAppGateway`

```php
interface WhatsAppGateway
{
    public function sendTemplate(
        string $toPhone,
        string $templateName,
        string $locale,
        array $variables,
        string $idempotencyKey,
    ): GatewayResult;

    public function verifyWebhook(Request $request): bool;
    public function parseInbound(Request $request): ?InboundMessage;
    public function healthCheck(): GatewayHealth;
}
```

**التنفيذ:** `MetaCloudApiGateway`

**قيود مفروضة بقرار العميل ([ADR-013](18-ADRs.md)):**
- **لا توجد دالة إرسال حر.** القوالب فقط — وهو أيضًا شرط Meta خارج نافذة الخدمة.
- **لا توجد دالة رد على رسالة واردة.** الوارد يُخزَّن في `whatsapp_inbound`
  ولا يُوجَّه آليًا، ويراه أصحاب `messaging.inbound.view` فقط.

### `EmailGateway`

```php
interface EmailGateway
{
    public function send(EmailMessage $message, string $idempotencyKey): GatewayResult;
    public function handleBounce(Request $request): ?BounceEvent;
    public function healthCheck(): GatewayHealth;
}
```

**التنفيذ:** `SesGateway` · الارتداد والشكاوى تُعطّل القناة للمستلم تلقائيًا.

### `PushGateway`

```php
interface PushGateway
{
    public function sendToTokens(array $tokens, PushMessage $message, string $idempotencyKey): GatewayResult;
    public function invalidateToken(string $token): void;
    public function healthCheck(): GatewayHealth;
}
```

**التنفيذ:** `FcmGateway` · التوكن غير الصالح يُبطَل في `user_devices` فورًا.

### `GatewayResult` الموحّد

```php
final readonly class GatewayResult
{
    public function __construct(
        public bool $accepted,
        public ?string $providerMessageId,
        public ?string $errorCode,       // ثابت لا يُترجم
        public ?string $errorMessage,
        public bool $isRetryable,        // يحدد إعادة المحاولة
        public array $raw = [],
    ) {}
}
```

`isRetryable` هو ما يقرر إعادة المحاولة — لا نوع الاستثناء ولا رمز HTTP.

---

## 5. التخزين

### `ObjectStorage`

```php
interface ObjectStorage
{
    public function put(string $path, string|resource $contents, array $options = []): string;
    public function signedUrl(string $path, int $ttlMinutes): string;
    public function delete(string $path): void;
    public function exists(string $path): bool;
    public function size(string $path): int;
}
```

**التنفيذ:** `R2Storage` عبر `league/flysystem-aws-s3-v3`.

**قاعدة صارمة:** كل ملف خاص. **لا رابط عام لأي ملف** يخص طالبًا —
الوصول عبر `signedUrl` بمدة محدودة فقط.

### `RecordingArchive`

```php
interface RecordingArchive
{
    public function archive(string $sourcePath, ArchiveTarget $target): ArchiveResult;
    public function exists(string $archivePath): bool;
    public function restoreUrl(string $archivePath, int $ttlMinutes): ?string;
    public function healthCheck(): GatewayHealth;
}
```

**التنفيذ:** `GoogleDriveArchive` (الافتراضي) · `R2ColdArchive` · `NullArchive`

**مسار الأرشفة** يتبع `recordings.storage.archive_path_pattern`:
```
{year}/{month}/{program}/{group}/{session_date}-{session_id}
```
منظَّم عمدًا حتى يستطيع العميل الرجوع للتسجيل يدويًا من الدرايف
دون الحاجة للمنصة.

**الحارس:** `archive()` تُتبَع دائمًا بـ `exists()` قبل حذف النسخة الساخنة.
حذف بلا تأكيد أرشفة = فقدان دائم.

---

## 6. سلوك الفشل الموحّد

```
استدعاء
   │
   ├── نجح ──────────────────────> النتيجة
   │
   ├── خطأ عابر (شبكة · 5xx · 429)
   │      └── إعادة محاولة: 1s · 4s · 15s · 60s · 180s
   │             └── فشل نهائي ──> تسجيل + تنبيه + وضع في طابور الفشل
   │
   └── خطأ منطقي (401 · 400 · قالب مرفوض)
          └── لا إعادة محاولة ──> استثناء مجال + تنبيه فوري للإدارة
```

### قاطع الدائرة

| المعامل | القيمة |
|---------|--------|
| عتبة الفتح | 5 إخفاقات متتالية |
| مدة الفتح | 120 ثانية |
| حالة نصف مفتوح | استدعاء واحد للاختبار |
| عند الفتح | تنبيه لأصحاب `system.alerts` |

**الفصل المباشر استثناء:** فتح القاطع على `VirtualClassroomProvider`
يُصعَّد فورًا لا بعد دقيقتين — حصة جارية لا تحتمل الانتظار.

---

## 7. الاختبارات

| النوع | كيف |
|-------|-----|
| اختبارات الوحدة | تنفيذ `Null` أو `Fake` — **بلا شبكة إطلاقًا** |
| اختبارات العقد | حمولات مسجَّلة حقيقية من كل مزوّد في `tests/Fixtures/` |
| اختبارات التكامل | مجموعة `@group external` — تعمل يدويًا لا في CI |
| اختبارات الويب هوك | توقيع صالح ينجح · توقيع فاسد يرد 401 · تكرار لا يضاعف |

**كل مزوّد يحتاج قبل اعتماده:**
1. `Null` يعمل بلا إعدادات.
2. اختبار يثبت أن الخطأ العابر يُعاد وأن المنطقي لا يُعاد.
3. اختبار يثبت أن التوقيع الفاسد يُرفض.
4. اختبار يثبت أن `healthCheck()` يعكس حالة حقيقية.

---

## 8. إضافة مزوّد جديد

1. اكتب Adapter في `Infrastructure/Providers/`.
2. سجّله في ملف الإعدادات المناسب تحت مفتاحه.
3. نفّذ **كل** دوال الواجهة — لا `throw new NotImplemented`.
   عدم الدعم يُعلَن في `capabilities()` لا باستثناء.
4. أضف حمولات الويب هوك في `tests/Fixtures/<provider>/`.
5. شغّل اختبارات العقد المشتركة على التنفيذ الجديد.
6. سجّل ADR يشرح لماذا أُضيف وما البدائل المرفوضة.
