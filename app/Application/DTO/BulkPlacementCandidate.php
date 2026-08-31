<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * نتيجة فحص طالب واحد قبل التسكين الجماعي.
 *
 * تُعرض للمستخدم بالاسم والكود وسبب مترجم — لا معرّفات داخلية.
 */
final readonly class BulkPlacementCandidate
{
    private function __construct(
        public string $applicationId,
        public ?string $studentProfileId,
        public string $name,
        public string $code,
        public bool $eligible,
        public ?string $reason,
        public bool $alreadyMember,
    ) {}

    public static function eligible(
        string $applicationId,
        string $studentProfileId,
        string $name,
        string $code,
    ): self {
        return new self($applicationId, $studentProfileId, $name, $code, true, null, false);
    }

    /** موجود بالفعل في المجموعة — لا يُعدّ فشلًا، ولا يُنشئ عضوية ثانية. */
    public static function alreadyMember(
        string $applicationId,
        string $studentProfileId,
        string $name,
        string $code,
        string $reason,
    ): self {
        return new self($applicationId, $studentProfileId, $name, $code, false, $reason, true);
    }

    public static function rejected(
        string $applicationId,
        ?string $studentProfileId,
        string $name,
        string $code,
        string $reason,
    ): self {
        return new self($applicationId, $studentProfileId, $name, $code, false, $reason, false);
    }
}
