<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ملكية الجداول — docs/08-module-boundaries.md قسم 4
|--------------------------------------------------------------------------
| الموديول يكتب في جداوله فقط، وجدول واحد له مالك واحد بالضبط.
| هذا الفاحص نصي بحت (بلا Pest Arch): يقرأ ملفات الهجرات تحت
| modules/NAME/database/migrations ويستخرج أسماء الجداول من Schema::create
| و Schema::table ثم يقارنها بخريطة tests/Architecture/table_ownership.php.
|
| يفشل عند: جدول لموديول آخر، جدول بلا مالك مسجَّل، أو اسم غير قابل للتحديد.
*/

const TABLE_OWNERSHIP_MIGRATION_VERBS = ['create', 'table'];

$repoRoot = dirname(__DIR__, 2);

$tableOwnership = require __DIR__.'/table_ownership.php';

/** مالك الجدول: مطابقة تامة أولًا ثم الأنماط التي تحوي *. */
$ownerOfTable = static function (string $table) use ($tableOwnership): ?string {
    if (array_key_exists($table, $tableOwnership)) {
        return $tableOwnership[$table];
    }

    foreach ($tableOwnership as $pattern => $module) {
        if (!str_contains((string) $pattern, '*')) {
            continue;
        }

        $regex = '/^'.str_replace('\*', '.*', preg_quote((string) $pattern, '/')).'$/';

        if (preg_match($regex, $table) === 1) {
            return (string) $module;
        }
    }

    return null;
};

/** كل ملفات هجرات الموديولات مرتبة. */
$migrationFiles = static function () use ($repoRoot): array {
    $files = glob($repoRoot.'/modules/*/database/migrations/*.php') ?: [];

    return array_values(array_map(
        static fn (string $file): string => str_replace('\\', '/', (string) realpath($file)),
        $files,
    ));
};

/**
 * يستخرج انتهاكات فعل معطى (create أو table):
 * يعيد قائمة أسطر بصيغة "المسار:السطر — الوصف".
 *
 * @return list<string>
 */
$violationsForVerb = static function (string $verb) use ($migrationFiles, $repoRoot, $ownerOfTable): array {
    $violations = [];

    foreach ($migrationFiles() as $file) {
        $relative = ltrim(str_replace('\\', '/', substr($file, strlen((string) realpath($repoRoot)) + 1)), '/');
        $module = '';
        if (preg_match('#^modules/([^/]+)/#', $relative, $m) === 1) {
            $module = $m[1];
        }

        $contents = (string) file_get_contents($file);

        // أسماء غير حرفية: Schema::create( ... ما ليس اقتباسًا مباشرًا.
        preg_match_all(
            '/Schema::'.$verb.'\s*\(\s*(?![\'"])(\S)/',
            $contents,
            $dynamic,
            PREG_OFFSET_CAPTURE,
        );

        foreach ($dynamic[1] as [$match, $offset]) {
            $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
            $violations[] = "{$relative}:{$line} — Schema::{$verb} باسم ديناميكي غير قابل للتحديد («{$match}…»)";
        }

        // أسماء حرفية: 'users' أو "users".
        preg_match_all(
            "/Schema::{$verb}\s*\(\s*(['\"])([^'\"]+)\\1/",
            $contents,
            $tables,
            PREG_OFFSET_CAPTURE,
        );

        foreach ($tables[2] as [$tableName, $offset]) {
            $line = substr_count(substr($contents, 0, (int) $offset), "\n") + 1;
            $owner = $ownerOfTable($tableName);

            if ($owner === null) {
                $violations[] = "{$relative}:{$line} — جدول «{$tableName}» غير مسجل في خريطة الملكية table_ownership.php";

                continue;
            }

            if ($owner !== $module) {
                $violations[] = "{$relative}:{$line} — «{$module}» يلامس جدول «{$tableName}» وهو ملك الموديول «{$owner}»";
            }
        }
    }

    sort($violations);

    return $violations;
};

it('لا ينشئ موديول جدولًا لا يملكه عبر Schema::create', function () use ($violationsForVerb): void {
    expect($violationsForVerb('create'))->toBe([]);
});

it('لا يعدّل موديول جدولًا لا يملكه عبر Schema::table', function () use ($violationsForVerb): void {
    expect($violationsForVerb('table'))->toBe([]);
});

it('يرفض أسماء جداول ديناميكية غير قابلة للتحديد في الهجرات', function () use ($repoRoot): void {
    $violations = [];

    foreach (glob($repoRoot.'/modules/*/database/migrations/*.php') ?: [] as $file) {
        $relative = ltrim(str_replace('\\', '/', substr(
            (string) realpath((string) $file),
            strlen((string) realpath($repoRoot)) + 1,
        )), '/');
        $contents = (string) file_get_contents((string) $file);

        preg_match_all('/Schema::(?:create|table)\s*\(\s*(?![\'"])(\S)/', $contents, $dynamic, PREG_OFFSET_CAPTURE);

        foreach ($dynamic[1] as [$token, $offset]) {
            $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
            $violations[] = "{$relative}:{$line} — Schema::create/table باسم ديناميكي غير قابل للتحديد («{$token}…»)";
        }
    }

    sort($violations);

    expect($violations)->toBe([]);
});
