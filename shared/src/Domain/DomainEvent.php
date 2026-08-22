<?php

declare(strict_types=1);

namespace Shared\Domain;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

/**
 * الأساس لكل Domain Event في المنصة.
 *
 * الأحداث هي وسيلة التواصل الافتراضية بين الموديولات. الموديول الذي يملك
 * الكيان هو وحده من ينشر الحدث؛ بقية الموديولات تستمع فقط.
 *
 * قواعد:
 *  - الحدث يحمل معرّفات (ids) وقيَمًا بدائية — أبدًا Eloquent models.
 *  - الحدث يصف ما حدث بصيغة الماضي: SessionCompleted وليس CompleteSession.
 *  - الحدث غير قابل للتعديل بعد إنشائه.
 *
 * السجل الكامل للأحداث: docs/09-domain-events.md
 */
abstract class DomainEvent
{
    use Dispatchable;

    public readonly string $eventId;

    public readonly CarbonImmutable $occurredAt;

    /**
     * معرّف المستخدم الذي تسبب في الحدث — null لو كان الحدث آليًا (نظام/مجدول).
     */
    public readonly ?string $actorId;

    /**
     * معرّف يربط كل الأحداث الناتجة عن نفس الطلب — لتتبّع السلسلة في التدقيق.
     */
    public readonly string $correlationId;

    public function __construct(?string $actorId = null, ?string $correlationId = null)
    {
        $this->eventId = (string) Str::ulid();
        $this->occurredAt = CarbonImmutable::now('UTC');
        $this->actorId = $actorId ?? auth()->id();
        $this->correlationId = $correlationId ?? (string) (request()?->header('X-Correlation-Id') ?: Str::ulid());
    }

    /**
     * الاسم المستقر للحدث كما يظهر في السجلات والتدقيق: sessions.completed
     */
    abstract public function name(): string;

    /**
     * الموديول المالك للحدث.
     */
    abstract public function module(): string;

    /**
     * حمولة الحدث للتسجيل والتدقيق — معرّفات وقيَم فقط.
     *
     * @return array<string, mixed>
     */
    abstract public function payload(): array;

    /**
     * @return array<string, mixed>
     */
    final public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'name' => $this->name(),
            'module' => $this->module(),
            'occurred_at' => $this->occurredAt->toIso8601String(),
            'actor_id' => $this->actorId,
            'correlation_id' => $this->correlationId,
            'payload' => $this->payload(),
        ];
    }
}
