<?php

declare(strict_types=1);

namespace Modules\SupportBot\Application\Services;

use Illuminate\Support\Collection;
use Modules\SupportBot\Domain\Enums\BotAudience;
use Modules\SupportBot\Domain\Enums\BotTopic;
use Modules\SupportBot\Domain\Enums\EntryKind;
use Modules\SupportBot\Domain\Models\BotEntry;

/**
 * حلّ نصوص البوت: التوجيهات والمعرفة والردود المعدّة.
 *
 * ترتيب الحل: اللغة في الحلقة الخارجية، والمؤسسة قبل العام في الداخلية — نفس
 * دلالة TemplateRenderer في الإشعارات. أي أن صفًّا عامًّا بلغة المستخدم يفوز
 * على صف مؤسسة بلغة أخرى، وهو الصواب: أن يقرأ بلغته أهم من أن يقرأ صياغة
 * مخصَّصة بلغة لا يفهمها.
 *
 * لا كاش هنا عمدًا. التحرير من اللوحة يجب أن يظهر في الرسالة التالية مباشرة؛
 * كاش بدقائق كان سيجعل الأدمن يعدّل نصًّا ويظنه لم يُحفظ. الاستعلام على فهرس
 * ومحدود الحجم، ونحفظ نتيجته داخل الطلب الواحد فقط.
 *
 * @phpstan-type ResolvedEntry array{key: string, title: string|null, body: string}
 */
final class KnowledgeResolver
{
    /** @var array<string, BotEntry|null> */
    private array $memo = [];

    /** نص ردّ معدّ بمفتاحه. يعيد null إذا لم يوجد صف فعّال. */
    public function reply(string $organizationId, string $key, string $locale): ?string
    {
        $entry = $this->resolveOne($organizationId, EntryKind::Reply, $key, $locale);

        return $entry?->body;
    }

    /**
     * التوجيهات الفعّالة لهذه الفئة، مرتّبة بالأولوية.
     *
     * @return list<string>
     */
    public function instructions(string $organizationId, BotAudience $audience, string $locale): array
    {
        return $this->bodiesOf(
            $this->entriesOfKind($organizationId, EntryKind::Instruction, $locale)
                ->filter(static fn (BotEntry $entry): bool => $entry->servesAudience($audience)),
        );
    }

    /**
     * مدخلات المعرفة التي تخدم هذا الموضوع وهذه الفئة.
     *
     * المدخلة بلا موضوع تُعتبر عامة وتُضمّ دائمًا — وهي الطريقة التي تكتب بها
     * الأكاديمية معلومة تنفع في كل سؤال (اسم الأكاديمية، مواعيد العمل).
     *
     * @return list<string>
     */
    public function knowledge(string $organizationId, BotTopic $topic, BotAudience $audience, string $locale): array
    {
        return $this->bodiesOf(
            $this->entriesOfKind($organizationId, EntryKind::Knowledge, $locale)
                ->filter(static fn (BotEntry $entry): bool => $entry->servesAudience($audience))
                ->filter(static fn (BotEntry $entry): bool => $entry->topic === null || $entry->topic === $topic->value),
        );
    }

    /**
     * @param Collection<int, BotEntry> $entries
     * @return list<string>
     */
    private function bodiesOf(Collection $entries): array
    {
        return $entries
            ->sortBy([['priority', 'asc'], ['key', 'asc']])
            ->map(static fn (BotEntry $entry): string => trim($entry->body))
            ->filter(static fn (string $body): bool => $body !== '')
            ->values()
            ->all();
    }

    /**
     * كل صفوف نوع معيّن بعد تطبيق تغطية المؤسسة للعام، صفًّا صفًّا بالمفتاح.
     *
     * @return Collection<int, BotEntry>
     */
    private function entriesOfKind(string $organizationId, EntryKind $kind, string $locale): Collection
    {
        $rows = BotEntry::query()
            ->active()
            ->forOrganizationOrGlobal($organizationId)
            ->where('kind', $kind->value)
            ->whereIn('locale', $this->localeChain($locale))
            ->get();

        $byKey = [];

        foreach ($rows as $row) {
            $byKey[$row->key] ??= [];
            $byKey[$row->key][] = $row;
        }

        $resolved = [];

        foreach ($byKey as $candidates) {
            $winner = $this->pick($candidates, $organizationId, $locale);

            if ($winner instanceof BotEntry) {
                $resolved[] = $winner;
            }
        }

        return new Collection($resolved);
    }

    private function resolveOne(string $organizationId, EntryKind $kind, string $key, string $locale): ?BotEntry
    {
        $memoKey = $organizationId.'|'.$kind->value.'|'.$key.'|'.$locale;

        if (array_key_exists($memoKey, $this->memo)) {
            return $this->memo[$memoKey];
        }

        $candidates = BotEntry::query()
            ->active()
            ->forOrganizationOrGlobal($organizationId)
            ->where('kind', $kind->value)
            ->where('key', $key)
            ->whereIn('locale', $this->localeChain($locale))
            ->get()
            ->all();

        return $this->memo[$memoKey] = $this->pick($candidates, $organizationId, $locale);
    }

    /**
     * اللغة أولًا ثم المؤسسة — انظر شرح الصنف.
     *
     * @param list<BotEntry> $candidates
     */
    private function pick(array $candidates, string $organizationId, string $locale): ?BotEntry
    {
        foreach ($this->localeChain($locale) as $candidateLocale) {
            $inLocale = array_values(array_filter(
                $candidates,
                static fn (BotEntry $entry): bool => $entry->locale === $candidateLocale,
            ));

            if ($inLocale === []) {
                continue;
            }

            foreach ($inLocale as $entry) {
                if ($entry->organization_id === $organizationId) {
                    return $entry;
                }
            }

            foreach ($inLocale as $entry) {
                if ($entry->organization_id === null) {
                    return $entry;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function localeChain(string $locale): array
    {
        $fallback = config('app.fallback_locale');
        $chain = [$locale];

        if (is_string($fallback) && $fallback !== '') {
            $chain[] = $fallback;
        }

        // العربية قاعدة إلزامية في هذا المشروع، والإنجليزية آخر ملاذ.
        $chain[] = 'ar';
        $chain[] = 'en';

        return array_values(array_unique($chain));
    }
}
