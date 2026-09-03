<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| اختبارات هذا الموديول ترث Tests\TestCase حتى يتوفر تطبيق Laravel كامل.
| بدونه لا يعمل __() ولا config() وتفشل الاختبارات بخطأ translator غير موجود.
*/

pest()->extend(TestCase::class)->in('Unit', 'Feature');

/*
| RefreshDatabase على المسارين معًا. كان محصورًا بـFeature بينما
| Unit/Pest.php يطبّقه على Unit — ربطان متعارضان على المجلد نفسه وأيّهما يفوز
| غير محسوم. النتيجة: اختبارات Unit التي تستعمل factories تنجح أو تسقط
| بـ«relation organizations does not exist» بحسب ترتيب التشغيل العشوائي.
*/
pest()->use(RefreshDatabase::class)->in('Unit', 'Feature');
