<?php

declare(strict_types=1);

namespace Shared\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * كل الكيانات تستخدم ULID كمفتاح أساسي.
 *
 * لماذا ULID وليس auto-increment:
 *  - المعرّف لا يكشف عدد الطلاب أو حجم النشاط.
 *  - يمكن توليده قبل الحفظ (مفيد في العمليات المركّبة والأحداث).
 *  - قابل للفرز زمنيًا، عكس UUIDv4 — أفضل لأداء الفهارس في PostgreSQL.
 */
trait HasUlid
{
    use HasUlids;

    public function getKeyType(): string
    {
        return 'string';
    }

    public function getIncrementing(): bool
    {
        return false;
    }
}
