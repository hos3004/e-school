<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

it('renders the public marketing home for guests', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->component('Marketing/Home'));
});

it('renders every public marketing page', function (string $uri, string $component): void {
    $this->get($uri)
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->component($component));
})->with([
    ['/about', 'Marketing/About'],
    ['/programs', 'Marketing/Programs/Index'],
    ['/projects', 'Marketing/Projects'],
    ['/activities', 'Marketing/Activities'],
    ['/faq', 'Marketing/Faq'],
    ['/contact', 'Marketing/Contact'],
    ['/privacy', 'Marketing/Legal'],
    ['/terms', 'Marketing/Legal'],
]);

it('renders every supported program page', function (string $program): void {
    $this->get("/programs/{$program}")
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Marketing/Programs/Show')
            ->where('program', $program));
})->with(['quran', 'children', 'coding', 'professional', 'family']);

it('returns the branded not found page for an unknown program', function (): void {
    $this->get('/programs/not-a-program')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page): Assert => $page->component('Errors/404'));
});
