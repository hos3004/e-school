<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Application\Console;

use Illuminate\Console\Command;
use Modules\VirtualClassroom\Domain\Contracts\SupportsWebhookRegistration;
use Modules\VirtualClassroom\Domain\Contracts\VirtualClassroomProvider;
use Modules\VirtualClassroom\Domain\Exceptions\ClassroomProviderException;

/** يسجل webhook المزوّد أو يعرضه أو يحذفه، من دون طباعة أي سر. */
final class ManageClassroomWebhook extends Command
{
    protected $signature = 'classroom:webhook
                            {action=list : list|register|remove}
                            {--callback= : Callback URL to register}
                            {--meeting= : Limit the hook to one external meeting}
                            {--hook= : Hook identifier to remove}';

    protected $description = 'Manage classroom-provider webhooks.';

    public function handle(VirtualClassroomProvider $provider): int
    {
        if (!$provider instanceof SupportsWebhookRegistration) {
            $this->error(__('virtualclassroom::messages.webhook_unsupported', [
                'provider' => $provider->name(),
            ]));

            return self::FAILURE;
        }

        try {
            return match ((string) $this->argument('action')) {
                'list' => $this->list($provider),
                'register' => $this->register($provider),
                'remove' => $this->remove($provider),
                default => $this->unknownAction((string) $this->argument('action')),
            };
        } catch (ClassroomProviderException $exception) {
            $this->error($exception->getMessage());
            $this->line(__('virtualclassroom::messages.smoke_reason', ['reason' => $exception->reason]));

            return self::FAILURE;
        }
    }

    private function list(SupportsWebhookRegistration $provider): int
    {
        $meeting = $this->option('meeting');
        $hooks = $provider->registeredWebhooks(is_string($meeting) && $meeting !== '' ? $meeting : null);
        $this->line(__('virtualclassroom::messages.webhook_count', ['count' => count($hooks)]));

        foreach ($hooks as $hook) {
            $this->line('  - '.__('virtualclassroom::messages.webhook_row', [
                'hook' => $hook->hookId,
                'callback' => $hook->callbackUrl,
                'meeting' => $hook->externalId ?? __('virtualclassroom::messages.webhook_scope_global'),
            ]));
        }

        return self::SUCCESS;
    }

    private function register(SupportsWebhookRegistration $provider): int
    {
        $callbackUrl = $this->callbackUrl();
        $meeting = $this->option('meeting');
        $hook = $provider->registerWebhook(
            $callbackUrl,
            is_string($meeting) && $meeting !== '' ? $meeting : null,
        );

        $this->info(__('virtualclassroom::messages.webhook_registered', [
            'hook' => $hook->hookId,
            'callback' => $hook->callbackUrl,
        ]));

        return self::SUCCESS;
    }

    private function remove(SupportsWebhookRegistration $provider): int
    {
        $hookId = $this->option('hook');
        if (!is_string($hookId) || $hookId === '') {
            $this->error(__('virtualclassroom::messages.webhook_hook_required'));

            return self::FAILURE;
        }

        $provider->removeWebhook($hookId);
        $this->info(__('virtualclassroom::messages.webhook_removed', ['hook' => $hookId]));

        return self::SUCCESS;
    }

    private function callbackUrl(): string
    {
        $option = $this->option('callback');
        if (is_string($option) && $option !== '') {
            return $option;
        }

        $configured = config('virtual-classroom.providers.bigbluebutton.webhook_callback_url');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return route('classroom.webhook');
    }

    private function unknownAction(string $action): int
    {
        $this->error(__('virtualclassroom::messages.smoke_unknown_action', ['action' => $action]));

        return self::FAILURE;
    }
}
