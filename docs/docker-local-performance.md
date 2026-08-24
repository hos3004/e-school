# مسار Docker السريع على Windows

المصدر يظل bind mount لدعم hot reload، لكن `vendor` و`node_modules` ينتقلان إلى
named volumes داخل Linux VM حتى لا يدفع كل طلب تكلفة NTFS metadata.

## التهيئة بعد تغيير lock files

```bash
docker compose -f docker-compose.yml -f docker-compose.fast.yml --profile tools \
  run --rm dependency-cache
```

## التشغيل اليومي

```bash
docker compose -f docker-compose.yml -f docker-compose.fast.yml up -d
```

> ملاحظة: عند إعادة إنشاء حاوية `app` يجب إعادة تشغيل `nginx` بعدها حتى يعيد
> حل اسم `app` في شبكة Docker وإلا يعيد 502 مؤقتًا:
>
> ```bash
> docker compose -f docker-compose.yml -f docker-compose.fast.yml restart nginx
> ```

## إعداد OPcache وPHP-FPM

ملف OPcache الافتراضي تطويري: `validate_timestamps=1` مع `revalidate_freq=15`
ثانية — كل فحص timestamps عبر bind mount (9p) يكلف حتى ~3ms لكل ملف، لذا
الفحص المتكرر في كل طلب كان سببًا رئيسيًا للبطء. التعديل في كود المشروع يظهر
خلال 15 ثانية كحد أقصى دون إعادة تشغيل. JIT معطل في التطوير. ملف
`docker/php/opcache.production.ini` هو المقترح للإنتاج (`validate_timestamps=0`)
ويُستخدم عند بناء صورة الإنتاج غير القابلة للتعديل. إعداد PHP-FPM التطويري
يرفع التوازي إلى `pm.max_children=12`؛ لا يُعد ذلك بديلًا عن إصلاح الاستعلامات
أو قياس TTFB.

## تخزين إطار العمل المؤقت (config / routes / events)

الاستدعاءات الحية لفحص الملفات عبر المونت مكلفة، لذا تُخزَّن في بيئة التطوير:

```bash
docker compose -f docker-compose.yml -f docker-compose.fast.yml exec app \
  sh -c "php artisan config:cache && php artisan route:cache && php artisan event:cache && php artisan filament:optimize"
```

**بعد أي تغيير في `.env` أو `config/` أو المسارات أو مستمعي الأحداث أعِد الأمر
نفسه.** للتنظيف الكامل:

```bash
docker compose -f docker-compose.yml -f docker-compose.fast.yml exec app \
  sh -c "php artisan optimize:clear && php artisan filament:clear-cache"
```

مسار `/` متحكم (`App\Http\Controllers\HomeController`) وليس closure تحديدًا
لأن `route:cache` يرفض closures.

## قياس الأداء

قياس داخلي (زمن الخادم وعدد الاستعلامات لكل صفحة ممثلة، بحسابات الديمو):

```bash
docker compose exec -T app php scripts/bench-pages.php
```

قياس خارجي TTFB من مضيف Windows عبر PowerShell:

```powershell
foreach ($p in @('/up','/login','/admin/login')) {
  curl.exe -s --noproxy '*' -o NUL "http://localhost:8090$p"
  foreach ($i in 1..3) {
    curl.exe -s --noproxy '*' --max-time 120 -o NUL `
      -w "$p %{time_starttransfer}`n" "http://localhost:8090$p"
  }
}
```

اختبار regression لعدد الاستعلامات (memoization الصلاحيات والجغرافيا):

```bash
docker compose exec -T app php scripts/test-isolated.php tests/Feature/Performance
```
