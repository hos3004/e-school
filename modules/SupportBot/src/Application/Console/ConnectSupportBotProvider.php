<?php

declare(strict_types=1);

namespace Modules\SupportBot\Application\Console;

use Illuminate\Console\Command;
use Modules\Integrations\Domain\Contracts\LlmConnections;

/**
 * إدخال مفتاح مزوّد النموذج — الطريق الوحيد لذلك، وهو على الخادم لا في الشاشة.
 *
 * المفتاح لا يمر بشاشة يفتحها موظف ولا يُكتب في محادثة، ولا يُمرَّر وسيطًا في
 * سطر الأمر حيث يبقى في سجلّ الأوامر: يُقرأ مخفيًّا من الطرفية، أو من الدخل
 * القياسي حين يُمرَّر بأنبوب.
 *
 * الحفظ لا يشغّل البوت. الاتصال يبدأ «معلّقًا»، والتشغيل قرار منفصل بزر ظاهر
 * وسبب مكتوب من قسم The Bot — فلا يبدأ البوت بالرد على أحد لمجرد إدخال مفتاح.
 */
final class ConnectSupportBotProvider extends Command
{
    protected $signature = 'support-bot:connect
        {organization : معرّف المؤسسة}
        {--actor= : معرّف حساب الأدمن الذي يُدخل المفتاح، للتدقيق}
        {--reason= : سبب الإدخال، للتدقيق}
        {--base-url= : عنوان المزوّد إن اختلف عن الافتراضي}
        {--skip-verify : الحفظ دون التحقق من المفتاح لدى المزوّد}';

    protected $description = 'حفظ مفتاح مزوّد النموذج لبوت الدعم مشفّرًا، دون تشغيل البوت.';

    public function handle(LlmConnections $connections): int
    {
        $organizationId = (string) $this->argument('organization');
        $actorId = trim((string) $this->option('actor'));
        $reason = trim((string) $this->option('reason'));
        $baseUrl = trim((string) ($this->option('base-url') ?: config('llm.providers.anthropic.base_url')));

        if (strlen($organizationId) !== 26 || strlen($actorId) !== 26) {
            $this->error((string) __('supportbot::console.ids_required'));

            return self::INVALID;
        }

        if (mb_strlen($reason) < 3) {
            $this->error((string) __('supportbot::console.reason_required'));

            return self::INVALID;
        }

        $apiKey = $this->readKey();

        if ($apiKey === '') {
            $this->error((string) __('supportbot::console.key_required'));

            return self::INVALID;
        }

        if (!(bool) $this->option('skip-verify')) {
            $failure = $connections->verify($apiKey, $baseUrl);

            if ($failure !== null) {
                $this->error((string) __('supportbot::console.verify_failed', ['reason' => $failure]));

                return self::FAILURE;
            }
        }

        $connections->save($organizationId, $apiKey, $baseUrl, $actorId, $reason);

        $this->info((string) __('supportbot::console.saved'));

        return self::SUCCESS;
    }

    private function readKey(): string
    {
        if ($this->input->isInteractive() && stream_isatty(STDIN)) {
            return trim((string) $this->secret((string) __('supportbot::console.key_prompt')));
        }

        $line = fgets(STDIN);

        return $line === false ? '' : trim($line);
    }
}
