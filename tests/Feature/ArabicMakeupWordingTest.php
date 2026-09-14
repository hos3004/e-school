<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * «تلافي» مصطلح متروك: المستعمل هو «الحصة التعويضية».
 *
 * الحارس هنا لأن الكلمة كانت متناثرة في ملفات ترجمة عدة موديولات، فمن السهل
 * أن تعود مع أول نص جديد يكتبه أحد من ذاكرة المصطلح القديم. المعرّفات نفسها
 * (`makeup` في الـenum ومفاتيح config والأعمدة ومفاتيح الترجمة) لا يمسّها هذا.
 */
final class ArabicMakeupWordingTest extends TestCase
{
    public function test_no_arabic_translation_file_still_says_talafi(): void
    {
        $root = dirname(__DIR__, 2);
        $files = [
            ...(glob($root.'/resources/lang/ar/*.php') ?: []),
            ...(glob($root.'/modules/*/resources/lang/ar/*.php') ?: []),
        ];

        self::assertNotEmpty($files, 'لم يُعثر على ملفات الترجمة العربية.');

        $offenders = [];

        foreach ($files as $file) {
            if (str_contains((string) file_get_contents($file), 'تلافي')) {
                $offenders[] = str_replace($root.'/', '', $file);
            }
        }

        self::assertSame([], $offenders, 'ملفات ترجمة ما زالت تستعمل «تلافي» بدل «الحصة التعويضية».');
    }
}
