<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Console\Support\WhatsappCampaignData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\StoreWhatsappCampaignRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Modules\Messaging\Application\Actions\CreateWhatsappCampaignAction;
use Modules\Messaging\Application\Actions\StartWhatsappCampaignAction;
use Modules\Messaging\Application\Actions\StopWhatsappCampaignAction;
use Modules\Messaging\Application\Services\CampaignMessageComposer;
use Modules\Messaging\Application\Services\CampaignRecipientListBuilder;
use Modules\Messaging\Application\Services\CampaignRecipientListParser;
use Modules\Messaging\Domain\Models\WhatsappCampaign;
use Shared\Support\BusinessRuleViolation;

/**
 * حملات واتساب في الكونسول: معاينة القائمة، ثم إنشاؤها مسودّة، ثم بدؤها.
 *
 * المعاينة لا تكتب شيئًا: المرسِل يرى كم رقمًا قُبل وكم رُفض ولماذا، ونصَّ
 * الرسالة كما سيصل لأول مستلم باسمه، قبل أن يُنشئ الحملة أصلًا.
 */
final class WhatsappCampaignController extends Controller
{
    public function __construct(
        private readonly CampaignRecipientListParser $parser,
        private readonly CampaignRecipientListBuilder $listBuilder,
        private readonly CampaignMessageComposer $composer,
        private readonly WhatsappCampaignData $data,
    ) {}

    /**
     * محاكاة القائمة قبل الحفظ.
     */
    public function preview(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);
        abort_unless($user->can('notifications.outbox.create'), 403);

        $input = $request->validate([
            'recipients_text' => ['nullable', 'string', 'max:500000'],
            'recipients_file' => ['nullable', 'file', 'max:10240', 'mimes:csv,txt,xlsx,xls,ods'],
            'body' => ['nullable', 'string', 'max:20000'],
        ]);

        $list = $this->listBuilder->build($this->rows($request, $input));
        $body = (string) ($input['body'] ?? '');
        $first = $list['accepted'][0] ?? null;

        return response()->json([
            'accepted_count' => count($list['accepted']),
            'rejected_count' => count($list['rejected']),
            'duplicates' => $list['duplicates'],
            // عيّنة لا القائمة كاملة: قائمة بألفي رقم لا تُقرأ على الشاشة.
            'accepted_sample' => array_slice($list['accepted'], 0, 50),
            'rejected' => array_slice($list['rejected'], 0, 200),
            'sample_text' => $first === null ? null : $this->composer->compose($body, $first['name']),
        ]);
    }

    public function store(
        StoreWhatsappCampaignRequest $request,
        CreateWhatsappCampaignAction $create,
    ): RedirectResponse {
        $data = $request->validated();
        $user = $request->user();
        abort_if($user === null, 401);

        /** @var list<UploadedFile> $media */
        $media = array_values(array_filter(
            (array) $request->file('media', []),
            static fn (mixed $file): bool => $file instanceof UploadedFile,
        ));

        try {
            $campaign = $create->execute(
                organizationId: (string) $user->getAttribute('organization_id'),
                actorId: (string) $user->getAuthIdentifier(),
                name: (string) $data['name'],
                body: (string) $data['body'],
                reason: (string) $data['reason'],
                rows: $this->rows($request, $data),
                media: $media,
                delayMinSeconds: (int) $data['delay_min_seconds'],
                delayMaxSeconds: (int) $data['delay_max_seconds'],
            );
        } catch (BusinessRuleViolation $error) {
            return back()->withErrors(['recipients_text' => $error->getMessage()]);
        }

        return back()->with('success', (string) __('console_whatsapp.campaigns.created', [
            'count' => $campaign->total_recipients,
        ]));
    }

    public function start(
        Request $request,
        WhatsappCampaign $campaign,
        StartWhatsappCampaignAction $start,
    ): RedirectResponse {
        $this->authorizeCampaign($request, 'start', $campaign);

        try {
            $start->execute($campaign);
        } catch (BusinessRuleViolation $error) {
            return back()->withErrors(['campaign' => $error->getMessage()]);
        }

        return back()->with('success', (string) __('console_whatsapp.campaigns.started'));
    }

    public function stop(
        Request $request,
        WhatsappCampaign $campaign,
        StopWhatsappCampaignAction $stop,
    ): RedirectResponse {
        $this->authorizeCampaign($request, 'stop', $campaign);

        $input = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $campaign = $stop->execute($campaign, (string) $input['reason']);
        } catch (BusinessRuleViolation $error) {
            return back()->withErrors(['campaign' => $error->getMessage()]);
        }

        return back()->with('success', (string) __('console_whatsapp.campaigns.stopped', [
            'count' => $campaign->total_recipients - $campaign->sent_count - $campaign->failed_count,
        ]));
    }

    /**
     * تقدّم الحملة — تستفتيه الشاشة دوريًا أثناء الإرسال.
     */
    public function show(Request $request, WhatsappCampaign $campaign): JsonResponse
    {
        $this->authorizeCampaign($request, 'view', $campaign);

        return response()->json([
            'campaign' => $this->data->summary($campaign),
            'problems' => $this->data->problems($campaign),
        ]);
    }

    /**
     * الحملة تُقرأ بالمعرّف من الرابط، فعزل المؤسسة يفرضه فحص السياسة لا الاستعلام.
     */
    private function authorizeCampaign(Request $request, string $ability, WhatsappCampaign $campaign): void
    {
        $user = $request->user();

        abort_if($user === null, 401);
        abort_unless($user->can($ability, $campaign), 403);
    }

    /**
     * صفوف القائمة من الملف ومن النص معًا — الملف أولًا ثم ما أضافه المرسِل.
     *
     * @param array<string, mixed> $data
     * @return list<array{name: string|null, phone_input: string}>
     */
    private function rows(Request $request, array $data): array
    {
        $rows = [];
        $file = $request->file('recipients_file');

        if ($file instanceof UploadedFile) {
            $rows = $this->parser->fromFile($file);
        }

        $text = trim((string) ($data['recipients_text'] ?? ''));

        if ($text !== '') {
            $rows = [...$rows, ...$this->parser->fromText($text)];
        }

        return $rows;
    }
}
