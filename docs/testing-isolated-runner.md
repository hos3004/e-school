# تشغيل اختبارات Laravel المعزولة

لا تشغّل `vendor/bin/pest` أو `php artisan test` مباشرة. ملف `phpunit.xml` لا يحدد
قاعدة افتراضية عمدًا، و`Tests\TestCase` يرفض الإقلاع قبل `RefreshDatabase` ما لم
يأتِ التشغيل من الـrunner الآمن.

## تشغيل موجّه

```bash
docker compose exec -T -e TEST_AGENT_ID=codex app \
  php scripts/test-isolated.php modules/Organization/tests/Feature
```

## الجولة الكاملة

```bash
docker compose exec -T -e TEST_AGENT_ID=codex app php scripts/test-isolated.php
```

ينشئ كل استدعاء قاعدة باسم
`eschool_testing_<agent>_<UTC timestamp>_<random>`، ويمرر `TEST_RUN_TOKEN` مطابقًا،
ويعزل cache/session/queue/mail داخل الذاكرة، ويعزل filesystem وcompiled views وLaravel
caches في مسار خاص بالتشغيل. تبقى بادئات Redis/Horizon فريدة أيضًا لأي اختبار يتصل
بهما صراحةً.

## إثبات تشغيلين متزامنين

```bash
docker compose exec -T -e TEST_AGENT_ID=proof app \
  php scripts/test-isolated.php --parallel-proof
```

يشغّل الأمر عمليتي Pest متزامنتين؛ كل واحدة تنفذ migrations في قاعدة مستقلة وتكتب
ملف إثبات داخل root مستقل.

## التنظيف عند انقطاع التشغيل قسرًا

التنظيف تلقائي داخل `finally`. إذا قُتلت الحاوية، استخدم الاسم الكامل الذي طبعه
الـrunner فقط:

```bash
docker compose exec -T app php scripts/test-isolated.php \
  --cleanup eschool_testing_<agent>_<timestamp>_<random>
```

أمر التنظيف يرفض `eschool` و`eschool_testing` وكل اسم لا يطابق صيغة قاعدة مولّدة،
ولا يستخدم glob أو حذفًا عامًا.
