<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Inertia\ProvidesInertiaProperties;
use Inertia\RenderContext;

final class PageTranslations implements ProvidesInertiaProperties
{
    /** @return iterable<string, mixed> */
    public function toInertiaProperties(RenderContext $context): iterable
    {
        return ['translations' => fn (): array => $this->forComponent($context->component, app()->getLocale())];
    }

    /** @return array<string, string> */
    public function forComponent(string $component, string $locale): array
    {
        $portal = Arr::dot($this->dictionary('portal', $locale));
        $public = str_starts_with($component, 'Auth/')
            || str_starts_with($component, 'Marketing/')
            || str_starts_with($component, 'Errors/');

        if ($public) {
            $portal = array_filter($portal, static fn (string $key): bool => in_array(
                explode('.', $key)[0], ['app', 'auth', 'actions', 'common', 'navigation', 'locales'], true,
            ), ARRAY_FILTER_USE_KEY);
        }

        foreach ($this->namespaces($component) as $namespace) {
            $portal = [...$portal, ...Arr::dot([$namespace => $this->dictionary($namespace, $locale)])];
        }

        return array_map(static fn (mixed $value): string => (string) $value, array_filter($portal, is_scalar(...)));
    }

    /** @return list<string> */
    private function namespaces(string $component): array
    {
        if (str_starts_with($component, 'Marketing/') || str_starts_with($component, 'Errors/')) {
            return ['marketing'];
        }

        if (str_starts_with($component, 'Auth/')) {
            if (str_starts_with($component, 'Auth/Learning')) {
                return ['learning'];
            }

            return in_array($component, ['Auth/RegisterStudent', 'Auth/RegistrationSubmitted', 'Auth/ApplicationStatus'], true)
                ? ['public_registration', 'console_people']
                : [];
        }

        $shared = ['profile_completion', 'session_pay', 'teacher_visibility'];
        if (str_starts_with($component, 'Console/')) {
            return [...$shared, 'learning', 'console', 'console_dashboard', 'console_sessions', 'console_group',
                'console_directory', 'console_profiles', 'console_people', 'console_courses', 'console_messaging',
                'console_quran', 'console_settings', 'console_registration', 'console_followup', 'console_dues',
                'console_session_review'];
        }

        if (str_starts_with($component, 'Learning/')) {
            return [...$shared, 'learning', 'learning_library', 'console_people', 'console_profiles', 'console_quran'];
        }

        return [...$shared, 'console_quran'];
    }

    /** @return array<string, mixed> */
    private function dictionary(string $namespace, string $locale): array
    {
        $base = Lang::get($namespace, [], 'ar');
        $fallback = Lang::get($namespace, [], (string) config('app.fallback_locale', 'en'));
        $translated = Lang::get($namespace, [], $locale);

        return array_replace_recursive(
            is_array($base) ? $base : [],
            is_array($fallback) ? $fallback : [],
            is_array($translated) ? $translated : [],
        );
    }
}
