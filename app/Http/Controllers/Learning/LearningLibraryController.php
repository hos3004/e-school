<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Content\Domain\Enums\MaterialType;
use Modules\Content\Domain\Models\CourseMaterial;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LearningLibraryController extends Controller
{
    public function __construct(private readonly LearningLibraryData $data) {}

    public function index(Request $request, string $kind): Response
    {
        $data = $this->data->all($request, $kind);
        $search = $request->query('search');
        $search = is_string($search) ? mb_substr(trim($search), 0, 120) : '';
        $type = $request->query('type');
        $type = in_array($type, ['file', 'link'], true) ? $type : '';
        $course = $request->query('course');
        $course = is_string($course) && in_array($course, array_column($data['courses'], 'id'), true) ? $course : '';
        $items = array_values(array_filter($data['items'], static fn (array $item): bool => ($search === '' || mb_stripos($item['title'].' '.$item['description'].' '.$item['course'], $search) !== false)
            && ($type === '' || $type === $item['kind']) && ($course === '' || $course === $item['courseId'])));
        $perPage = max(1, (int) config('content.library.per_page'));
        $rawPage = $request->query('page', 1);
        $page = max(1, min(is_scalar($rawPage) ? (int) $rawPage : 1, max(1, (int) ceil(count($items) / $perPage))));
        $paginator = new LengthAwarePaginator(array_slice($items, ($page - 1) * $perPage, $perPage), count($items), $perPage, $page,
            ['path' => $request->url(), 'query' => ['search' => $search, 'type' => $type, 'course' => $course]]);

        return Inertia::render('Learning/Library', [
            'kind' => $kind, 'materials' => $paginator, 'courses' => $data['courses'], 'indexUrl' => $data['url'],
            'filters' => compact('search', 'type', 'course'), 'selected' => null,
            'timezone' => app(ConsoleContext::class)->forRequest($request)['timezone'],
        ]);
    }

    public function show(Request $request, string $material, string $kind): Response
    {
        $this->record($request, $kind, $material);
        $data = $this->data->all($request, $kind);
        $selected = collect($data['items'])->firstWhere('id', $material);
        abort_if($selected === null, 404);

        return Inertia::render('Learning/Library', [
            'kind' => $kind, 'materials' => null, 'courses' => [], 'indexUrl' => $data['url'],
            'filters' => ['search' => '', 'type' => '', 'course' => ''], 'selected' => $selected,
            'timezone' => app(ConsoleContext::class)->forRequest($request)['timezone'],
        ]);
    }

    public function open(Request $request, string $material, string $kind): StreamedResponse|RedirectResponse
    {
        $record = $this->record($request, $kind, $material);
        if ($record->type === MaterialType::Link) {
            $url = (string) $record->external_url;
            abort_unless(filter_var($url, FILTER_VALIDATE_URL)
                && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
                && parse_url($url, PHP_URL_USER) === null && parse_url($url, PHP_URL_PASS) === null, 404);

            return redirect()->away($url)->withHeaders(['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
        }
        $path = (string) $record->path;
        $disk = (string) $record->disk;
        $directoryAllowed = collect((array) config('content.library.storage_directories'))->contains(
            static fn (string $directory): bool => str_starts_with($path, trim($directory, '/').'/'),
        );
        abort_unless($directoryAllowed && $path !== '' && !str_contains($path, '..')
            && !preg_match('/[\\\\\x00-\x1F\x7F]/', $path)
            && in_array($disk, (array) config('content.library.storage_disks'), true), 404);
        $storage = Storage::disk($disk);
        abort_unless($storage->exists($path), 404);
        $stream = $storage->readStream($path);
        abort_unless(is_resource($stream), 404);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $title = preg_replace('/[\/\\\\\x00-\x1F\x7F]+/u', '-', $record->title['ar'] ?? __('learning_library.untitled'));
        $name = mb_substr($title ?: __('learning_library.untitled'), 0, 150).($extension === '' ? '' : '.'.$extension);

        return response()->streamDownload(static function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, $name, ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private function record(Request $request, string $kind, string $id): CourseMaterial
    {
        $scope = $this->data->scope($request, $kind);
        $material = CourseMaterial::query()->forOrganization($scope['organizationId'])
            ->whereIn('course_id', $scope['courseIds'])->active()->whereKey($id)->firstOrFail();
        Gate::authorize('viewForLearning', [$material, $scope['courseIds']]);

        return $material;
    }
}
