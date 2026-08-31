<?php

declare(strict_types=1);

namespace Modules\Students\Domain\ValueObjects;

use Modules\Students\Domain\Enums\RegistrationQuestionType;

/** سؤال تسجيل سمحت الإدارة بالفلترة به، بصورته المعروضة في الواجهة. */
final readonly class FilterableQuestionData
{
    /**
     * @param list<string> $options خيارات سؤال الاختيار — فارغة للسؤال الرقمي
     */
    public function __construct(
        public string $id,
        public string $label,
        public RegistrationQuestionType $type,
        public array $options,
    ) {}

    /** مفتاح الفلتر في جدول Filament — مشتق من معرّف السؤال لا من نصه. */
    public function filterKey(): string
    {
        return 'question_'.$this->id;
    }
}
