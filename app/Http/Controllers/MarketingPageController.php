<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

final class MarketingPageController extends Controller
{
    /** @var list<string> */
    private const PROGRAMS = ['quran', 'children', 'coding', 'professional', 'family'];

    public function about(): Response
    {
        return Inertia::render('Marketing/About');
    }

    public function programs(): Response
    {
        return Inertia::render('Marketing/Programs/Index');
    }

    public function program(string $program): Response
    {
        abort_unless(in_array($program, self::PROGRAMS, true), 404);

        return Inertia::render('Marketing/Programs/Show', ['program' => $program]);
    }

    public function projects(): Response
    {
        return Inertia::render('Marketing/Projects');
    }

    public function activities(): Response
    {
        return Inertia::render('Marketing/Activities');
    }

    public function faq(): Response
    {
        return Inertia::render('Marketing/Faq');
    }

    public function contact(): Response
    {
        return Inertia::render('Marketing/Contact');
    }

    public function privacy(): Response
    {
        return Inertia::render('Marketing/Legal', ['document' => 'privacy']);
    }

    public function terms(): Response
    {
        return Inertia::render('Marketing/Legal', ['document' => 'terms']);
    }
}
