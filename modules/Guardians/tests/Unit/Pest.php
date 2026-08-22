<?php

declare(strict_types=1);

/*
| ربط اختبارات Unit لموديول Guardians بالتطبيق الكامل وقاعدة البيانات.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in(__DIR__);
