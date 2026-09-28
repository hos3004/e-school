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
 * المحروس بنفس تفويض عرض المنشور هو الطريقة الوحيدة للوصول إليها.
 *
 * لا نثق بحقلي disk/path كما وردا في JSON المخزَّن: attachments عمود حر
 * الشكل تاريخيًا، وStoreWallPostRequest لا يقيّد شكله، فطلب نشر يحمل
 * attachments مفبركة كان يقدر يجعل هذا المسار يخدم أي ملف على أي قرص —
 * قرص التسجيلات أو أي مؤسسة أخرى مثلًا — لمجرد أن صاحب الطلب يملك منشورًا
 * واحدًا يقدر يفوَّض على عرضه. القرص ثابت دومًا من الإعداد، والمسار يُرفض
 * إن لم يبدأ بمجلد حائط هذه المجموعة بالذات كما يكتبه الرفع الفعلي وحده.
 */
final class ShowWallAttachmentController extends Controller
{
    public function __invoke(ClassWallPost $post, int $index): StreamedResponse
    {
        Gate::authorize('view', $post);

        $attachments = $post->attachments ?? [];
        $attachment = $attachments[$index] ?? null;

        abort_if(!is_array($attachment), 404);

        $disk = (string) config('messaging.wall.attachments.disk');
        $directory = trim((string) config('messaging.wall.attachments.directory'), '/');
        $expectedPrefix = $directory.'/'.((string) $post->group_id).'/';

        $path = (string) ($attachment['path'] ?? '');

        abort_unless(str_starts_with($path, $expectedPrefix), 404);
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path);
    }
}
