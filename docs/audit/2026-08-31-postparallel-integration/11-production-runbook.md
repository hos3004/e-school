# دليل التشغيل الإنتاجي

## المتطلبات

- Linux، Docker/Compose، PostgreSQL، Redis، ومساحة تخزين دائمة.
- أسرار فعلية خارج Git. ابدأ من `.env.example` ولا تنسخ بيانات عرض إلى production.
- اجعل `APP_ENV=production` و`APP_DEBUG=false`، واضبط `APP_KEY` وURL وقاعدة البيانات وRedis والبريد والتخزين.
- استخدم `CLASSROOM_PROVIDER="null"` لتعطيل الفصل الخارجي صراحة، أو `bigbluebutton` مع URL/secret من مدير الأسرار.
- اترك `FEATURE_ASSESSMENTS=false` و`FEATURE_CERTIFICATES=false` و`FEATURE_BADGES=false` ما لم يعتمد نطاق جديد.

## تثبيت وإطلاق

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

لا تشغّل `migrate:fresh` في production. `DatabaseSeeder` و`IdentitySeeder` محميان من إنشاء بيانات عرض خارج local/testing. أنشئ مدير المنصة بعد تجهيز المؤسسة وrole `platform_admin`:

```bash
php artisan eschool:admin --email=admin@example.com --name="Platform Admin"
```

أدخل كلمة مرور سرية تفاعليًا بطول 16 حرفًا على الأقل؛ لا تمررها في shell history ولا تطبعها في logs. تأكد من عدم وجود `public/hot` في artifact الإنتاجي.

## العمليات الطويلة

- شغّل Horizon تحت process supervisor وأعده بعد كل release: `php artisan horizon` ثم `php artisan horizon:terminate` عند النشر المتدرج.
- أضف cron واحدًا كل دقيقة: `* * * * * php /app/artisan schedule:run`.
- شغّل Reverb فقط عند اعتماد realtime مع host/port/TLS الصحيح، وتابع health والاتصال من reverse proxy.
- افصل web وqueue وscheduler وReverb في services قابلة لإعادة التشغيل، مع health checks وlog rotation.

## النسخ الاحتياطي والاستعادة

قبل migrations:

```bash
pg_dump --format=custom --file=eschool-before-release.dump "$DATABASE_URL"
sha256sum eschool-before-release.dump > eschool-before-release.dump.sha256
pg_restore --list eschool-before-release.dump >/dev/null
```

اختبر الاستعادة في قاعدة disposable أولًا:

```bash
createdb eschool_restore_drill
pg_restore --exit-on-error --no-owner --dbname=eschool_restore_drill eschool-before-release.dump
DB_DATABASE=eschool_restore_drill php artisan migrate --force
DB_DATABASE=eschool_restore_drill php artisan migrate:status
dropdb eschool_restore_drill
```

لا تستبدل قاعدة الإنتاج أثناء الاختبار. احتفظ بالنسخة والـSHA خارج checkout وبصلاحيات محدودة.

## rollback

إذا فشل فحص ما بعد الإطلاق: أوقف traffic والworkers، أعد artifact السابق، واستعد dump ما قبل الإصدار إلى قاعدة جديدة ثم وجّه الاتصال إليها بعد التحقق. لا تستخدم `migrate:rollback` تلقائيًا إذا كانت migrations أو jobs قد كتبت بيانات؛ الاستعادة الكاملة هي المسار الآمن.

## فحوص ما بعد الإطلاق

- `/up` أو health endpoint، login، Dashboard، queue backlog، scheduler timestamps وReverb handshake.
- لا `local.ERROR` جديد، لا أسرار أو كلمات مرور في logs، ولا 404/403/500 غير متوقع.
- نفّذ checklist UAT المرافق قبل فتح الوصول للعميل.
