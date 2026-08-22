<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| اختبارات هذا الموديول ترث Tests\TestCase.
| الدوال المساعدة المشتركة في tests/Helpers.php المحمَّل عبر composer.
*/

pest()->extend(TestCase::class)->in('Unit', 'Feature');

pest()->use(RefreshDatabase::class)->in('Feature');
