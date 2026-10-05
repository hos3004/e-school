<?php

declare(strict_types=1);

namespace Modules\Students\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Students\Domain\Enums\RegistrationQuestionType;
use Shared\Concerns\HasUlid;

/**
 * سؤال تقييم يظهر في نموذج تسجيل الطالب العام، وتُدار من لوحة التحكم.
 *
 * @property string $id
 * @property string $organization_id
 * @property string|null $registration_form_id
 * @property array<string, string> $question نص السؤال بحسب اللغة (ar/en/fr)
 * @property RegistrationQuestionType $type
 * @property list<string>|null $options خيارات سؤال الاختيار
 * @property bool $is_required
 * @property bool $is_active
 * @property bool $is_filterable هل يُعرض هذا السؤال فلترًا في شاشة التسجيلات؟
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
final class RegistrationQuestion extends Model
{
    use HasUlid;
    use SoftDeletes;

    protected $table = 'registration_questions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'registration_form_id',
        'question',
        'type',
        'options',
        'is_required',
        'is_active',
        'is_filterable',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'question' => 'array',
            'type' => RegistrationQuestionType::class,
            'options' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'is_filterable' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function localizedQuestion(): string
    {
        $locale = app()->getLocale();

        return $this->question[$locale] ?? $this->question['ar'] ?? $this->question['en'] ?? '';
    }

    /** @return BelongsTo<RegistrationForm, $this> */
    public function registrationForm(): BelongsTo
    {
        return $this->belongsTo(RegistrationForm::class);
    }

    /**
     * @param Builder<RegistrationQuestion> $query
     * @return Builder<RegistrationQuestion>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * الأسئلة المفعّلة بترتيب العرض — ما يُعرض على نموذج التسجيل العام.
     *
     * @param Builder<RegistrationQuestion> $query
     * @return Builder<RegistrationQuestion>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('created_at');
    }

    /**
     * القائمة البيضاء للفلترة: الأسئلة المفعّلة التي سمحت الإدارة بالفلترة بها.
     *
     * النوع محكوم كذلك بقيد في قاعدة البيانات، فلا يصير سؤال نصي حر قابلًا
     * للفلترة حتى لو كُتبت القيمة يدويًا.
     *
     * @param Builder<RegistrationQuestion> $query
     * @return Builder<RegistrationQuestion>
     */
    public function scopeFilterable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('is_filterable', true)
            ->whereIn('type', [
                RegistrationQuestionType::Select->value,
                RegistrationQuestionType::Radio->value,
                RegistrationQuestionType::Number->value,
            ])
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }
}
