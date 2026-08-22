<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| اختبارات هذا الموديول ترث Tests\\TestCase.
| الدوال المساعدة المشتركة في tests/Helpers.php المحمَّل عبر composer.
*/

pest()->extend(Tests\TestCase::class)->in('Unit', 'Feature');

pest()->use(RefreshDatabase::class)->in('Feature');
