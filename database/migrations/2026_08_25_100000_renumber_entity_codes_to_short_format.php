<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * توحيد كل أكواد العرض على الصيغة القصيرة: حرف + ثلاثة أرقام (E001 · T001 · G001).
 *
 * قبل هذه الهجرة كانت الأكواد مختلطة (STU-0001، DEMO-T001، GRP-2026-001، وأسوأها
 * ULID كامل من ٢٦ خانة يكتبه AcceptRegistrationApplicationAction). الترقيم هنا
 * بالترتيب الزمني لإنشاء السجل، فأقدم طالب يأخذ E001.
 *
 * الكود للعرض فقط ولا يُربط به أي جدول آخر (الربط بـ id من نوع ULID)، لذا إعادة
 * الترقيم لا تكسر أي مفتاح أجنبي.
 */
return new class extends Migration
{
    public function up(): void
    {
        /** @var array<string, array{table: string, column: string, scope: string|null}> $entities */
        $entities = (array) config('codes.entities', []);
        $digits = max(1, (int) config('codes.digits', 3));

        foreach ($entities as $entity => $definition) {
            $prefix = (string) config("codes.prefixes.{$entity}");

            if ($prefix === '') {
                continue;
            }

            $this->renumber(
                table: $definition['table'],
                column: $definition['column'],
                scopeColumn: $definition['scope'] ?? null,
                prefix: $prefix,
                digits: $digits,
            );
        }
    }

    /**
     * لا رجوع: الأكواد القديمة لم تكن تتبع صيغة واحدة، فإعادة بنائها مستحيلة
     * وتخمينها أسوأ من تركها. التراجع يكون باستعادة نسخة احتياطية.
     */
    public function down(): void {}

    private function renumber(
        string $table,
        string $column,
        ?string $scopeColumn,
        string $prefix,
        int $digits,
    ): void {
        if (!DB::getSchemaBuilder()->hasTable($table)) {
            return;
        }

        $rows = DB::table($table)
            ->select(array_values(array_filter(['id', $column, $scopeColumn])))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        // عداد لكل نطاق على حدة حتى لا يصطدم كودان داخل نفس قيد التفرّد.
        $counters = [];

        // المرحلة الأولى: أكواد مؤقتة. الانتقال المباشر من STU-0002 إلى E001
        // قد يصطدم بكود موجود لسجل لم يُعالَج بعد، فنفرّغ المساحة أولًا.
        foreach ($rows as $row) {
            DB::table($table)
                ->where('id', $row->id)
                ->update([$column => '~'.$row->id]);
        }

        foreach ($rows as $row) {
            $scope = $scopeColumn === null ? '*' : (string) $row->{$scopeColumn};
            $counters[$scope] = ($counters[$scope] ?? 0) + 1;

            DB::table($table)
                ->where('id', $row->id)
                ->update([
                    $column => $prefix.str_pad((string) $counters[$scope], $digits, '0', STR_PAD_LEFT),
                ]);
        }
    }
};
