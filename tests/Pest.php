<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| ربط مجلدات الاختبار بأصناف TestCase
|--------------------------------------------------------------------------
| اختبارات المستودع تعيش في مكانين:
|   tests/{Unit,Feature,Architecture}      اختبارات على مستوى المنصة
|   modules/<Name>/tests/{Unit,Feature}    اختبارات كل موديول
|
| الاثنان يرثان Tests\TestCase حتى يتوفر تطبيق Laravel كامل — بدونه
| لا يعمل __() ولا config() ولا الحاوية، وتفشل الاختبارات بخطأ
| "Target class [translator] does not exist".
|
| اختبارات المعمارية فحوص نصية على المستودع ولا تلمس قاعدة البيانات.
*/

pest()->extend(Tests\TestCase::class)->in('Feature', 'Unit', 'Architecture');

pest()->extend(Tests\TestCase::class)->in('../modules');

/*
| اختبارات Feature وحدها هي التي تحتاج قاعدة بيانات نظيفة لكل اختبار.
| اختبارات Unit تبقى بلا قاعدة بيانات حتى تظل سريعة.
*/
pest()->use(RefreshDatabase::class)->in('Feature', '../modules/*/tests/Feature');
