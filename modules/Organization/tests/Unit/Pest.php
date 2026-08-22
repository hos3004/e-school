<?php

declare(strict_types=1);

/*
| ربط اختبارات Unit لموديول Organization بالتطبيق الكامل وقاعدة البيانات.
|
| الـ factories تعيش في database/factories خارج مسار autoload لذا نحمّلها
| هنا يدويًا قبل أي اختبار، ونستخدمها مباشرة عبر XxxFactory::new().
*/

foreach (glob(__DIR__.'/../../database/factories/*.php') ?: [] as $factoryFile) {
    require_once $factoryFile;
}

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class)->in(__DIR__);
