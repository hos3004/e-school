<?php

declare(strict_types=1);

namespace Modules\Integrations\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Integrations\Domain\Enums\ConnectionStatus;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Modules\Integrations\Domain\Models\IntegrationProvider;

/**
 * بيانات تجريبية لموديول التكاملات: مزوّدون معروفون واتصال مُفعَّل واحد
 * لبيئة العرض.
 */
final class IntegrationsSeeder extends Seeder
{
    public function run(): void
    {
        $organizationId = $this->ensureOrganizationId();

        $providers = [
            ['key' => 'whatsapp_gateway', 'category' => 'messaging', 'driver' => 'whatsapp_cloud_api'],
            ['key' => 'video_conferencing', 'category' => 'video', 'driver' => 'zoom'],
            ['key' => 'payment_gateway', 'category' => 'payment', 'driver' => null],
        ];

        foreach ($providers as $index => $data) {
            $provider = IntegrationProvider::query()->firstOrCreate(
                ['key' => $data['key']],
                [
                    'name' => [
                        'ar' => __('integrations::messages.demo_provider_name', [
                            'name' => __('integrations::messages.provider_'.$data['key']),
                        ]),
                        'en' => ucfirst(str_replace('_', ' ', $data['key'])),
                    ],
                    'category' => $data['category'],
                    'driver' => $data['driver'],
                    'is_active' => true,
                ],
            );

            if ($index === 0 && $provider->wasRecentlyCreated) {
                IntegrationConnection::query()->firstOrCreate([
                    'organization_id' => $organizationId,
                    'provider_id' => (string) $provider->getKey(),
                ], [
                    'status' => ConnectionStatus::Active,
                    'settings' => ['default_locale' => 'ar'],
                    'activated_at' => now(),
                ]);
            }
        }
    }

    /**
     * المؤسسة يملكها موديول Organization — نستهلك الموجودة، وإن لم توجد
     * أنشئ سجلًا تجريبيًا مصغرًا كما يفعل باقي الموديولات في بيئة العرض.
     */
    private function ensureOrganizationId(): string
    {
        $existing = DB::table('organizations')->orderBy('created_at')->value('id');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $organizationId = '01JDEMOORGANIZATION0000000';

        DB::table('organizations')->insert([
            'id' => $organizationId,
            'name' => json_encode(['ar' => __('integrations::messages.demo_school_name'), 'en' => 'Demo School'], JSON_UNESCAPED_UNICODE),
            'slug' => 'demo-school',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $organizationId;
    }
}
