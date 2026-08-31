<!DOCTYPE html>
<html
    lang="{{ app()->getLocale() }}"
    dir="{{ in_array(app()->getLocale(), (array) config('app.rtl_locales', ['ar']), true) ? 'rtl' : 'ltr' }}"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body>
        @inertia

        {{-- طبقة النوافذ المنبثقة — مرة واحدة لكل الواجهات (Inertia) --}}
        @include('notifications::popups.layer')
    </body>
</html>
