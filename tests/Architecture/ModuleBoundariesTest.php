<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| حدود الموديولات — الفرض الآلي (docs/08-module-boundaries.md قسم 7)
|--------------------------------------------------------------------------
| هذه الاختبارات تُكتب قبل الموديولات نفسها، وتفشل CI فورًا عند:
|   1. استيراد Domain\Models من موديول آخر
|   2. استيراد أصناف موديول مختوم من خارجه (إلا عقوده المعلنة)
|   3. عقود تُرجع أو تستخدم نماذج Eloquent
|   4. ملف بلا declare(strict_types=1)
|   5. دوال تنقيح متروكة في الكود
|   6. اعتماد على طبقة أعلى من طبقة الموديول
|
| ملاحظة: الموديولات الفارغة الآن تُنتج طبقات فارغة في فاحص الـ AST،
| فتمر الاختبارات سليمة، وتبدأ الحماية تلقائيًا مع أول صنف يُكتب.
*/

use Illuminate\Database\Eloquent\Model;

// ── الخرائط الثابتة ──────────────────────────────────────────────────────────

$moduleLayers = require __DIR__.'/module_layers.php';

$modules = array_keys($moduleLayers);

/** الموديولات المختومة — مصدرها config/modules.php (sealed_domains). */
$sealed = ['Payroll', 'Billing', 'Audit', 'Identity'];

// ── أدوات مساعدة ─────────────────────────────────────────────────────────────

$namespacesOf = static function (array $names): array {
    return array_values(array_map(
        static fn (string $name): string => "Modules\\{$name}",
        $names,
    ));
};

$modelNamespacesOf = static function (array $names): array {
    return array_values(array_map(
        static fn (string $name): string => "Modules\\{$name}\\Domain\\Models",
        $names,
    ));
};

/**
 * أصناف العقود العامة لكل الموديولات، مشتقة من مساراتها على القرص:
 * modules/<Name>/src/Domain/Contracts/*.php → Modules\<Name>\Domain\Contracts\<Class>
 */
$contractClasses = [];
$repoRoot = dirname(__DIR__, 2);
foreach (glob($repoRoot.'/modules/*/src/Domain/Contracts/*.php') ?: [] as $contractFile) {
    $relative = str_replace('\\', '/', substr(
        (string) realpath((string) $contractFile),
        strlen((string) realpath($repoRoot)) + 1,
    ));
    if (preg_match('#^modules/([^/]+)/src/(.+)\.php$#', $relative, $m) === 1) {
        $contractClasses[] = 'Modules\\'.$m[1].'\\'.str_replace('/', '\\', $m[2]);
    }
}
$contractClasses = array_values(array_unique($contractClasses));

// ── القاعدة 1: لا استيراد لنماذج موديول آخر ─────────────────────────────────

foreach ($modules as $module) {
    $otherModules = array_values(array_filter(
        $modules,
        static fn (string $name): bool => $name !== $module,
    ));

    arch("حدود التغليف: موديول «{$module}» لا يستورد نماذج Domain Models من أي موديول آخر")
        ->expect("Modules\\{$module}")
        ->not->toUse($modelNamespacesOf($otherModules));
}

// ── القاعدة 2: الموديولات المختومة لا تُستورد من خارجها ─────────────────────

foreach ($modules as $module) {
    $otherSealed = array_values(array_filter(
        $sealed,
        static fn (string $name): bool => $name !== $module,
    ));

    // القناة الوحيدة للتعامل مع المختوم هي عقوده في Domain\Contracts.
    $sealedContracts = array_values(array_map(
        static fn (string $name): string => "Modules\\{$name}\\Domain\\Contracts",
        $otherSealed,
    ));

    arch("الموديولات المختومة: موديول «{$module}» لا يستورد أصناف الموديولات المختومة إلا عقودها المعلنة")
        ->expect("Modules\\{$module}")
        ->not->toUse($namespacesOf($otherSealed))
        ->ignoring($sealedContracts);
}

// ── القاعدة 3: العقود لا تكشف نماذج Eloquent ────────────────────────────────

arch('العقود العامة: لا يعتمد أي عقد في Domain Contracts على نموذج Eloquent الأساسي')
    ->expect($contractClasses)
    ->interfaces()
    ->not->toUse(Model::class);

arch('العقود العامة: لا يعتمد أي عقد في Domain Contracts على نموذج أي موديول حتى موديوله هو')
    ->expect($contractClasses)
    ->interfaces()
    ->not->toUse($modelNamespacesOf($modules));

// ── القاعدة 4: الأنواع الصارمة في كل مكان ───────────────────────────────────

arch('الأنواع الصارمة: كل صنف تحت modules و shared يعلن declare(strict_types=1)')
    ->expect(array_merge(['Shared'], $namespacesOf($modules)))
    ->toUseStrictTypes();

// ── القاعدة 5: لا دوال تنقيح متروكة ─────────────────────────────────────────

arch('نظافة الكود: لا استخدام لدوال التنقيح dd أو dump أو var_dump أو ray')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();

// ── القاعدة 6: اتجاه الاعتماد بين الطبقات محفوظ ─────────────────────────────

foreach ($modules as $module) {
    $layerOfModule = $moduleLayers[$module];

    /** الممنوع: كل موديول في نفس الطبقة أو أعلى منها — عدا الموديول نفسه. */
    $forbiddenModules = array_keys(array_filter(
        $moduleLayers,
        static fn (int $layer, string $name): bool => $name !== $module && $layer >= $layerOfModule,
        ARRAY_FILTER_USE_BOTH,
    ));

    if ($forbiddenModules === []) {
        continue; // Reporting (الطبقة 7) لا يوجد فوقه أحد — القاعدة فارغة عليه.
    }

    /*
     * عقد المستودع يسمح بالاعتماد العابر للطبقات عبر Public Contracts فقط.
     * القاعدة السابقة كانت تمنع namespace الموديول كاملًا هنا، فتناقض القاعدة
     * 2 أعلاه وتُسقط الاعتماد المشروع على Domain\Contracts.
     */
    $publicContractNamespaces = [];
    foreach ($forbiddenModules as $name) {
        $publicContractNamespaces[] = "Modules\\{$name}\\Domain\\Contracts";
        // عقود القراءة تعيد DTOs عامة بلا نماذج Eloquent.
        $publicContractNamespaces[] = "Modules\\{$name}\\Domain\\ValueObjects";
    }

    arch("اتجاه الطبقات: موديول «{$module}» في الطبقة {$layerOfModule} لا يعتمد على نفس طبقته أو ما فوقها")
        ->expect("Modules\\{$module}")
        ->not->toUse($namespacesOf($forbiddenModules))
        ->ignoring($publicContractNamespaces);
}
