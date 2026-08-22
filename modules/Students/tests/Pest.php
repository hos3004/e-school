<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| اختبارات هذا الموديول ترث Tests\TestCase حتى يتوفر تطبيق Laravel كامل.
| بدونه لا يعمل __() ولا config() وتفشل الاختبارات بخطأ translator غير موجود.
*/

pest()->extend(TestCase::class)->in('Unit', 'Feature');

pest()->use(RefreshDatabase::class)->in('Feature');
