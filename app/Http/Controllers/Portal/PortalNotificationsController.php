<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * صفحة الإشعارات المشتركة للبوابات الثلاث — نفس الواجهة بنطاق
 * المستخدم نفسه؛ الـAPI تحته يفرض الملكية، والدور هنا للتسمية
 * والتنقل فقط لا للتصريح.
 */
final class PortalNotificationsController extends Controller
{
    public function student(Request $request): Response
    {
        return $this->render($request, 'student');
    }

    public function teacher(Request $request): Response
    {
        return $this->render($request, 'teacher');
    }

    public function guardian(Request $request): Response
    {
        return $this->render($request, 'guardian');
    }

    private function render(Request $request, string $role): Response
    {
        return Inertia::render('Shared/Notifications', [
            'role' => $role,
        ]);
    }
}
