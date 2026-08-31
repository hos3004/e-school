<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Services;

use Illuminate\Support\Str;
use Modules\Identity\Domain\Contracts\OrganizationUsernamePrefixProvider;
use Modules\Identity\Domain\Contracts\UsernameSuggestionGateway;
use Modules\Identity\Domain\Models\User;

/**
 * يولّد أسماء دخول قابلة للاستخدام دون معرفة Identity بجدول إعدادات المؤسسة.
 */
final readonly class UsernameSuggester implements UsernameSuggestionGateway
{
    public function __construct(
        private OrganizationUsernamePrefixProvider $organizationUsernamePrefix,
    ) {}

    /**
     * @return list<string>
     */
    public function suggest(string $fullName, ?string $organizationId = null): array
    {
        $separator = (string) config('admission.username.separator');
        $minLength = (int) config('admission.username.min_length');
        $maxLength = (int) config('admission.username.max_length');
        $suggestionsCount = (int) config('admission.username.suggestions_count');
        $prefix = $this->resolvePrefix($organizationId, $separator);
        $parts = $this->nameParts($fullName, $separator);
        $first = $parts[0] ?? $prefix;
        $last = $parts === [] ? $prefix : $parts[count($parts) - 1];
        $initial = mb_substr($first, 0, 1);
        $reserved = array_values(array_map(
            fn (mixed $value): string => $this->normalize((string) $value, $separator),
            (array) config('admission.username.reserved', []),
        ));

        if (in_array($first, $reserved, true)) {
            $first = $prefix;
            $initial = mb_substr($first, 0, 1);
        }

        $tokens = [
            '{prefix}' => $prefix,
            '{sep}' => $separator,
            '{first}' => $first,
            '{last}' => $last,
            '{initial}' => $initial,
        ];
        $suggestions = [];

        foreach ((array) config('admission.username.patterns', []) as $pattern) {
            if (!is_string($pattern) || count($suggestions) >= $suggestionsCount) {
                break;
            }

            if (!str_contains($pattern, '{n}')) {
                $this->appendIfAvailable(
                    $this->candidate(strtr($pattern, $tokens), $separator, $minLength, $maxLength),
                    $reserved,
                    $suggestions,
                );

                continue;
            }

            $number = 1;

            while (count($suggestions) < $suggestionsCount) {
                $suffix = (string) $number;
                $rendered = strtr($pattern, [
                    ...$tokens,
                    '{n}' => '',
                ]);
                $this->appendIfAvailable(
                    $this->candidate($rendered, $separator, $minLength, $maxLength, $suffix),
                    $reserved,
                    $suggestions,
                );
                $number++;
            }
        }

        return array_slice($suggestions, 0, $suggestionsCount);
    }

    private function resolvePrefix(?string $organizationId, string $separator): string
    {
        if ($organizationId !== null) {
            $value = $this->organizationUsernamePrefix->forOrganization($organizationId);

            if (is_string($value) && trim($value) !== '') {
                return $this->normalize($value, $separator);
            }
        }

        return $this->normalize((string) config('admission.username.fallback_prefix'), $separator);
    }

    /** @return list<string> */
    private function nameParts(string $fullName, string $separator): array
    {
        $latinName = $this->transliterateArabicToLatin($fullName);

        return array_values(array_filter(array_map(
            fn (string $part): string => $this->normalize($part, $separator),
            preg_split('/\s+/u', trim($latinName)) ?: [],
        )));
    }

    private function candidate(
        string $value,
        string $separator,
        int $minLength,
        int $maxLength,
        string $preservedSuffix = '',
    ): string {
        $suffixLength = mb_strlen($preservedSuffix);
        $candidate = rtrim(
            mb_substr($this->normalize($value, $separator), 0, $maxLength - $suffixLength),
            $separator,
        ).$preservedSuffix;

        if (mb_strlen($candidate) >= $minLength) {
            return rtrim($candidate, $separator);
        }

        return rtrim(mb_substr($candidate.$separator.'user', 0, $maxLength), $separator);
    }

    /**
     * @param list<string> $reserved
     * @param list<string> $suggestions
     */
    private function appendIfAvailable(string $candidate, array $reserved, array &$suggestions): void
    {
        if ($candidate === ''
            || in_array($candidate, $reserved, true)
            || in_array($candidate, $suggestions, true)
            || !$this->isAvailable($candidate)) {
            return;
        }

        $suggestions[] = $candidate;
    }

    private function isAvailable(string $username): bool
    {
        return !User::query()->where('username', $username)->exists();
    }

    private function normalize(string $value, string $separator): string
    {
        return trim(strtolower(Str::slug(Str::ascii($value), $separator)), $separator);
    }

    private function transliterateArabicToLatin(string $text): string
    {
        $text = str_replace(['أحمد', 'احمد'], 'ahmed', $text);
        $map = [
            'أ' => 'a', 'إ' => 'a', 'آ' => 'a', 'ء' => 'a', 'ا' => 'a',
            'ب' => 'b', 'ت' => 't', 'ث' => 'th', 'ج' => 'j', 'ح' => 'h',
            'خ' => 'kh', 'د' => 'd', 'ذ' => 'dh', 'ر' => 'r', 'ز' => 'z',
            'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'd', 'ط' => 't',
            'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh', 'ف' => 'f', 'ق' => 'q',
            'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'ه' => 'h',
            'و' => 'w', 'ي' => 'y', 'ى' => 'a', 'ة' => 'h', 'ئ' => 'y', 'ؤ' => 'w',
        ];

        return (string) preg_replace('/[^a-zA-Z0-9\s]/', '', strtr($text, $map));
    }
}
