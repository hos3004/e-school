<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| اختبارات موديول AcademicReports ترث Tests\TestCase.
*/

pest()->extend(TestCase::class)->in('Unit', 'Feature');

pest()->use(RefreshDatabase::class)->in('Unit', 'Feature');
