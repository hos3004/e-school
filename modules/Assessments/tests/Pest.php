<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| اختبارات موديول Assessments ترث Tests\TestCase حتى يتوفر تطبيق Laravel كامل.
| اختبارات Feature وحدها تستخدم قاعدة البيانات مع RefreshDatabase.
*/

pest()->extend(TestCase::class)->in('Unit', 'Feature');

pest()->use(RefreshDatabase::class)->in('Feature');
