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

        $this->retireLegacyZoomProviders();

        $providers = [
            ['key' => 'whatsapp_gateway', 'category' => 'messaging', 'driver' => 'whatsapp_cloud_api'],
            ['key' => 'video_conferencing', 'category' => 'video', 'driver' => 'bigbluebutton'],
            ['key' => 'payment_gateway', 'category' => 'payment', 'driver' => null],
        ];

        foreach ($providers as $index => $data) {
            $provider = IntegrationProvider::query()->updateOrCreate(
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

            if ($index === 0) {
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
     * اتصالات Zoom القديمة لا تُعاد تسميتها إلى BBB وهي ما زالت فعّالة؛
     * تُعطّل أولًا وتُمحى أسرار demo، ثم يعاد استخدام سجل الفيديو العام لـBBB.
     */
    private function retireLegacyZoomProviders(): void
    {
        $legacyProviders = IntegrationProvider::query()
            ->where('driver', 'zoom')
            ->orWhere('key', 'zoom')
            ->get();

        foreach ($legacyProviders as $provider) {
            foreach ($provider->connections()->get() as $connection) {
                $changes = [
                    'credentials' => null,
                    'settings' => null,
                ];

                if ($connection->status->canTransitionTo(ConnectionStatus::Disabled)) {
                    $changes['status'] = ConnectionStatus::Disabled;
                    $changes['disabled_at'] = now();
                }

                // حتى الاتصال المعطّل/المنتهي لا يحتفظ بأسرار Zoom قديمة.
                $connection->forceFill($changes)->save();
            }

            if ($provider->key === 'zoom') {
                $provider->delete();
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
