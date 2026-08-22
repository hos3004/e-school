<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| اختبارات موديول Reporting ترث Tests\\TestCase.
| اختبارات Unit هنا تلمس قاعدة البيانات لأن قواعد العمل مرتبطة بها
| (فرادة event_id، ومفاتيح فريدة للوحات) — لذلك نفعّل RefreshDatabase.
*/

pest()->extend(TestCase::class)->in('Unit', 'Feature');

pest()->use(RefreshDatabase::class)->in('Unit', 'Feature');
