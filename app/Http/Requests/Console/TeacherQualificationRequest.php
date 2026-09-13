<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;

/**
 * مدخلات اعتماد كورسات المعلم وسحبها.
 *
 * الاعتماد يقبل أكثر من كورس دفعة واحدة، والسحب كورس واحد بعينه، وكلاهما يلزمه
 * سبب مكتوب لأن الاثنين يُسجَّلان في التدقيق. الصلاحية تُفحَص على السجل نفسه
 * في الـcontroller عبر Policy لأن القرار يتوقف على مؤسسة السجل لا على المستخدم وحده.
 */
final class TeacherQualificationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $reason = ['reason' => ['required', 'string', 'min:3', 'max:1000']];

        if ($this->isMethod('delete')) {
            return [...$reason, 'course_id' => ['required', 'string', 'ulid']];
        }

        return [
            ...$reason,
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['string', 'ulid', 'distinct'],
        ];
    }

    /** @return list<string> */
    public function courseIds(): array
    {
        /** @var array<int, mixed> $ids */
        $ids = (array) $this->validated('course_ids');

        return array_values(array_map(strval(...), $ids));
    }

    public function courseId(): string
    {
        return (string) $this->validated('course_id');
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }

    public function organizationId(): string
    {
        $id = $this->user()?->getAttribute('organization_id');
        abort_unless(is_string($id) && $id !== '', 403);

        return $id;
    }

    public function actorId(): string
    {
        return (string) $this->user()?->getAuthIdentifier();
    }
}
