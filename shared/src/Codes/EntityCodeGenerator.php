<?php

declare(strict_types=1);

namespace Shared\Codes;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * مولّد أكواد العرض القصيرة (E001 · T001 · G001 …).
 *
 * لماذا في `shared/` وليس داخل موديول: الكود ليس مفهومًا يملكه موديول بعينه —
 * كل موديول يحتاجه لجدوله هو. الخدمة لا تعرف أي Model ولا أي namespace خاص
 * بموديول؛ تتعامل مع (جدول، عمود) من `config/codes.php` فقط، فلا تكسر قواعد
 * الحدود في CLAUDE.md §2.
 *
 * التسلسل يُحسب من أعلى كود موجود بنفس البادئة، لا من عدد الصفوف — فحذف سجل
 * أو أرشفته لا يعيد استخدام كوده ولا يصطدم بقيد التفرّد.
 */
final class EntityCodeGenerator
{
    /**
     * الكود التالي المتاح للكيان.
     *
     * @param  string      $entity مفتاح من `codes.entities`
     * @param  string|null $scope  قيمة عمود النطاق حين يكون التفرّد مركّبًا (مثل levels.program_id)
     */
    public function next(string $entity, ?string $scope = null): string
    {
        $prefix = self::prefix($entity);
        $number = $this->highestNumber($entity, $prefix, $scope) + 1;

        // التوسّع بعد تجاوز السقف (E1000) أفضل من إسقاط العملية أو تكرار كود موجود.
        return $prefix.str_pad((string) $number, self::digits(), '0', STR_PAD_LEFT);
    }

    /**
     * الصيغة المقبولة للكيان — للتحقق في FormRequest أو حقل Filament.
     */
    public static function pattern(string $entity): string
    {
        return '/^'.preg_quote(self::prefix($entity), '/').'\d{'.self::digits().',}$/';
    }

    public static function prefix(string $entity): string
    {
        /** @var array<string, string> $prefixes */
        $prefixes = (array) config('codes.prefixes', []);

        if (!isset($prefixes[$entity])) {
            throw new InvalidArgumentException("Unknown code entity [{$entity}].");
        }

        return $prefixes[$entity];
    }

    public static function digits(): int
    {
        return max(1, (int) config('codes.digits', 3));
    }

    /**
     * أعلى رقم مستخدم حاليًا بهذه البادئة. الأكواد بصيغة أخرى (STU-0001) لا
     * تطابق النمط فتُتجاهل ولا تفسد التسلسل.
     */
    private function highestNumber(string $entity, string $prefix, ?string $scope): int
    {
        [$table, $column, $scopeColumn] = self::entity($entity);

        $query = DB::table($table)->whereRaw(
            "{$column} ~ ?",
            ['^'.$prefix.'[0-9]+$'],
        );

        $this->applyScope($query, $table, $scopeColumn, $scope);

        /** @var int|null $highest */
        $highest = $query->max(DB::raw("CAST(SUBSTRING({$column} FROM 2) AS INTEGER)"));

        return (int) $highest;
    }

    private function applyScope(Builder $query, string $table, ?string $scopeColumn, ?string $scope): void
    {
        if ($scopeColumn === null) {
            return;
        }

        if ($scope === null) {
            throw new InvalidArgumentException(
                "Code entity on [{$table}] requires a [{$scopeColumn}] scope value.",
            );
        }

        $query->where($scopeColumn, $scope);
    }

    /**
     * @return array{0: string, 1: string, 2: string|null}
     */
    private static function entity(string $entity): array
    {
        /** @var array<string, array{table: string, column: string, scope: string|null}> $entities */
        $entities = (array) config('codes.entities', []);

        if (!isset($entities[$entity])) {
            throw new InvalidArgumentException("Unknown code entity [{$entity}].");
        }

        return [
            $entities[$entity]['table'],
            $entities[$entity]['column'],
            $entities[$entity]['scope'] ?? null,
        ];
    }
}
