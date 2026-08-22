<?php

declare(strict_types=1);

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
        $id = (string) \Illuminate\Support\Str::ulid();
        \Illuminate\Support\Facades\DB::table('organizations')->insert([
            'id' => $id,
            'name' => json_encode(['ar' => 'مؤسسة الاختبار', 'en' => 'Test Org'], JSON_UNESCAPED_UNICODE),
            'slug' => 'test-'.strtolower(substr($id, -8)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return $id;
}



