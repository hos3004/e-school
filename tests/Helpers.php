<?php

declare(strict_types=1);
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
| دوال مساعدة عامة للاختبارات.
| تُحمَّل عبر composer autoload-dev files حتى تتوفر لكل الاختبارات،
| لأن Pest لا يحمّل Pest.php إلا من المجلدات المعرَّفة في phpunit.xml.
*/

/**
 * معرّف المؤسسة المستخدم في اختبارات هذا الموديول.
 */
function disciplineOrg(): string
{
    static $id = null;

    if ($id === null) {
        $id = (string) Str::ulid();
        DB::table('organizations')->insert([
            'id' => $id,
            'name' => json_encode(['ar' => 'مؤسسة الاختبار', 'en' => 'Test Org'], JSON_UNESCAPED_UNICODE),
            'slug' => 'test-'.strtolower(substr($id, -8)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return $id;
}
