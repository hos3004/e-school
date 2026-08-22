# E-School — Online School Management & Learning Platform

منصة إدارة مدرسة أونلاين متكاملة: تسجيل الطلاب، الجدولة، الفصول المباشرة، الحضور،
الواجبات والاختبارات، التقارير الأكاديمية، الانضباط، المراسلات، ورواتب المعلمين.

مبنية كـ **Modular Monolith** على Laravel — تطبيق واحد، حدود موديولات صارمة.

---

## المكوّنات

| الطبقة | التقنية |
|--------|---------|
| Backend | Laravel 13 · PHP 8.4 |
| Database | PostgreSQL 17 |
| Cache / Queue | Redis 7 · Laravel Horizon |
| Realtime | Laravel Reverb 1 (WebSockets) |
| لوحة الإدارة | Filament 5 |
| واجهة الطالب/المعلم/ولي الأمر | React 19 + TypeScript + Inertia 3 + Tailwind 4 |
| الفصل المباشر | BigBlueButton (خلف `VirtualClassroomProvider`) |
| التخزين | Cloudflare R2 · أرشفة التسجيلات إلى Google Drive |
| الإشعارات | In-App · Email (SES) · Push (FCM) · WhatsApp Cloud API |
| الاختبارات | Pest 5 (Unit / Feature / Arch) + Playwright |

---

## التشغيل السريع

المتطلب الوحيد على جهازك هو Docker. لا تحتاج PHP محليًا.

```bash
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app npm install && docker compose exec app npm run dev
```

| الخدمة | العنوان |
|--------|---------|
| التطبيق | http://localhost:8000 |
| لوحة الإدارة | http://localhost:8000/admin |
| Horizon | http://localhost:8000/horizon |
| Mailpit | http://localhost:8025 |

---

## بنية المستودع

```
app/            نواة Laravel الرفيعة (User, Providers فقط)
shared/         النواة المشتركة بين الموديولات (Domain Events, Value Objects, أدوات)
modules/        27 موديول — كل واحد Domain / Application / Infrastructure / Presentation
config/         إعدادات التطبيق وقواعد العمل القابلة للضبط
docs/           وثائق المعمارية — اقرأها قبل أي كود
docker/         صور وإعدادات بيئة التشغيل
```

---

## قبل ما تكتب سطر كود

اقرأ بالترتيب:

1. [`docs/01-PRD.md`](docs/01-PRD.md) — ما الذي نبنيه ولمن
2. [`docs/08-module-boundaries.md`](docs/08-module-boundaries.md) — من يكلّم من
3. [`docs/05-state-machines.md`](docs/05-state-machines.md) — دورات الحياة الأربع
4. [`docs/17-coding-standards.md`](docs/17-coding-standards.md) — معايير الكود
5. [`docs/21-definition-of-done.md`](docs/21-definition-of-done.md) — متى تقول "خلصت"

وللأجندة الحالية: [`docs/PROGRESS.md`](docs/PROGRESS.md)

---

## أوامر الجودة

```bash
composer lint    # فحص التنسيق (Pint)
composer fix     # إصلاح التنسيق
composer stan    # تحليل ثابت (PHPStan level 6)
composer test    # كل الاختبارات (Pest)
composer check   # الثلاثة معًا — يجب أن تمر قبل أي PR
```
