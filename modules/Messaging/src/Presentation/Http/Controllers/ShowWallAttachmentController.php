<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Modules\Messaging\Domain\Models\ClassWallPost;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * يخدم صورة مرفقة بمنشور حائط — القرص خاص عمدًا (local)، فهذا المسار
 * المحروس بنفس تفويض عرض المنشور هو الطريقة الوحيدة للوصول إليها؛ لا رابط
 * تخزين خام يتسرّب في استجابة الـAPI.
 */
final class ShowWallAttachmentController extends Controller
{
    public function __invoke(ClassWallPost $post, int $index): StreamedResponse
    {
        Gate::authorize('view', $post);

        $attachments = $post->attachments ?? [];
        $attachment = $attachments[$index] ?? null;

        abort_if(!is_array($attachment), 404);

        $disk = (string) ($attachment['disk'] ?? config('messaging.wall.attachments.disk'));
        $path = (string) ($attachment['path'] ?? '');

        abort_unless($path !== '' && Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path);
    }
}
