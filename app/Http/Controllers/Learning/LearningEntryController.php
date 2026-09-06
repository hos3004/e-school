<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class LearningEntryController extends Controller
{
    public function __invoke(Request $request, LearningDestination $destination): Response
    {
        return Inertia::render('Auth/LearningLogin', ['portals' => $destination->portals($request)]);
    }
}
