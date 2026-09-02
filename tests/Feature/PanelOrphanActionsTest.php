<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Identity\Domain\Models\User;
use Modules\Integrations\Domain\Enums\DeliveryStatus;
use Modules\Integrations\Domain\Models\IntegrationWebhookDelivery;
use Modules\Integrations\Presentation\Filament\Resources\IntegrationWebhookDeliveryResource;
use Modules\Messaging\Domain\Models\Message;
use Modules\Messaging\Presentation\Filament\Resources\MessageResource;
use Modules\Organization\Domain\Models\Organization;
use Modules\Reporting\Domain\Models\OrganizationSnapshot;
use Modules\Reporting\Presentation\Filament\Resources\OrganizationSnapshotResource;
use Tests\TestCase;

/**
 * إجراءات كانت مكتوبة ومختبَرة ولها سياسات — بلا زر يصل إليها.
 *
 * تتبّعُ كل Action ومَن يستدعيه أظهر أن ثلاثة منها لم تكن مربوطة بأي واجهة
 * إدارية: إعادة إدراج إيصال webhook ميت، وتعليم رسالة للمراجعة، والتقاط لقطة
 * تنظيمية. أسوأها الأول: تكامل خارجي يسقط ولا طريق تعافٍ من اللوحة.
 *
 * يُستدعى الزر هنا كما يستدعيه المستخدم، ويُقاس الأثر في قاعدة البيانات.
 */
final class PanelOrphanActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_a_dead_webhook_delivery_can_be_requeued_from_the_panel(): void
    {
        [$organizationId] = $this->actorWith(['integrations.delivery.view_any', 'integrations.delivery.requeue'], IntegrationWebhookDeliveryResource::class);

        $delivery = $this->deadDelivery($organizationId);

        Livewire::test(IntegrationWebhookDeliveryResource::getPages()['index']->getPage())
            ->callTableAction('requeue', $delivery)
            ->assertHasNoTableActionErrors();

        $delivery->refresh();

        self::assertSame(DeliveryStatus::Retrying, $delivery->status);
        self::assertNull($delivery->failed_at);
        self::assertNotNull($delivery->next_retry_at);
    }

    public function test_requeue_is_hidden_without_its_permission(): void
    {
        [$organizationId] = $this->actorWith(['integrations.delivery.view_any'], IntegrationWebhookDeliveryResource::class);

        $delivery = $this->deadDelivery($organizationId);

        Livewire::test(IntegrationWebhookDeliveryResource::getPages()['index']->getPage())
            ->assertTableActionHidden('requeue', $delivery);

        self::assertSame(DeliveryStatus::Dead, $delivery->refresh()->status);
    }

    /** الزر يختفي على غير الميت لأن الإجراء يرفضه — فلا نعرض مسارًا مآله خطأ. */
    public function test_requeue_is_hidden_for_a_delivery_that_is_not_dead(): void
    {
        [$organizationId] = $this->actorWith(['integrations.delivery.view_any', 'integrations.delivery.requeue'], IntegrationWebhookDeliveryResource::class);

        $delivery = $this->deadDelivery($organizationId, DeliveryStatus::Delivered);

        Livewire::test(IntegrationWebhookDeliveryResource::getPages()['index']->getPage())
            ->assertTableActionHidden('requeue', $delivery);
    }

    public function test_a_message_can_be_flagged_for_review_with_a_reason(): void
    {
        [$organizationId, $actor] = $this->actorWith(['message.send'], MessageResource::class);

        $message = $this->message($organizationId, $actor);

        Livewire::test(MessageResource::getPages()['index']->getPage())
            ->callTableAction('flag', $message, ['reason' => 'لغة غير لائقة تجاه زميل'])
            ->assertHasNoTableActionErrors();

        $message->refresh();

        self::assertTrue((bool) $message->is_flagged);
        self::assertSame('لغة غير لائقة تجاه زميل', $message->flagged_reason);
        self::assertSame((string) $actor->getKey(), (string) $message->moderated_by);
        self::assertNotNull($message->moderated_at);
    }

    public function test_flagging_requires_a_reason(): void
    {
        [$organizationId, $actor] = $this->actorWith(['message.send'], MessageResource::class);

        $message = $this->message($organizationId, $actor);

        Livewire::test(MessageResource::getPages()['index']->getPage())
            ->callTableAction('flag', $message, ['reason' => ''])
            ->assertHasTableActionErrors(['reason']);

        self::assertFalse((bool) $message->refresh()->is_flagged);
    }

    public function test_an_organization_snapshot_can_be_captured_on_demand(): void
    {
        [$organizationId] = $this->actorWith(['reporting.snapshot.view_any', 'reporting.snapshot.build'], OrganizationSnapshotResource::class);

        self::assertSame(0, OrganizationSnapshot::query()->count());

        Livewire::test(OrganizationSnapshotResource::getPages()['index']->getPage())
            ->callTableAction('capture_snapshot', null, ['snapshot_date' => Carbon::now('UTC')->toDateString()])
            ->assertHasNoTableActionErrors();

        $snapshot = OrganizationSnapshot::query()->sole();

        self::assertSame($organizationId, (string) $snapshot->organization_id);
    }

    private function deadDelivery(
        string $organizationId,
        DeliveryStatus $status = DeliveryStatus::Dead,
    ): IntegrationWebhookDelivery {
        $providerId = (string) Str::ulid();
        $connectionId = (string) Str::ulid();

        DB::table('integration_providers')->insert([
            'id' => $providerId,
            'key' => 'test-provider-'.strtolower((string) Str::ulid()),
            'name' => json_encode(['ar' => 'مزوّد', 'en' => 'Provider'], JSON_THROW_ON_ERROR),
            'category' => 'messaging',
            'is_active' => true,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        DB::table('integration_connections')->insert([
            'id' => $connectionId,
            'organization_id' => $organizationId,
            'provider_id' => $providerId,
            'status' => 'active',
            'credentials' => '',
            'settings' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        return IntegrationWebhookDelivery::query()->create([
            'connection_id' => $connectionId,
            'event_type' => 'session.completed',
            'direction' => 'outbound',
            'status' => $status,
            'payload' => ['id' => 1],
            'attempts' => 3,
            'failed_at' => $status === DeliveryStatus::Dead ? now('UTC') : null,
        ]);
    }

    private function message(string $organizationId, User $author): Message
    {
        $conversationId = (string) Str::ulid();

        // `created_by` و`sender_id` مفتاحان أجنبيان نحو users، فلا تصلح ULID
        // عشوائية — تُستخدم هوية فاعل حقيقي.
        DB::table('conversations')->insert([
            'id' => $conversationId,
            'organization_id' => $organizationId,
            'subject' => 'محادثة قيد الإشراف',
            'type' => 'direct',
            'is_moderated' => false,
            'created_by' => (string) $author->getKey(),
            'created_at' => now('UTC'),
        ]);

        return Message::query()->create([
            'organization_id' => $organizationId,
            'conversation_id' => $conversationId,
            'user_id' => (string) $author->getKey(),
            'body' => 'نص الرسالة قيد الإشراف',
            'attachments' => [],
            'is_flagged' => false,
        ]);
    }

    /**
     * @param list<string> $permissions
     * @param class-string $resource
     * @return array{0: string, 1: User}
     */
    private function actorWith(array $permissions, string $resource): array
    {
        $organization = Organization::factory()->create();
        $organizationId = (string) $organization->getKey();

        $actor = User::factory()->inOrganization($organizationId)->create();

        foreach ([...$permissions, 'admin.panel.access'] as $name) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $name],
                ['guard_name' => 'web'],
            );

            ModelHasPermission::query()->firstOrCreate([
                'permission_id' => (string) $permission->getKey(),
                'model_type' => $actor->getMorphClass(),
                'model_id' => (string) $actor->getAuthIdentifier(),
            ]);
        }

        app(PermissionGateRegistrar::class)->register();

        $this->actingAs($actor);
        session()->put('organization_id', $organizationId);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // الطلب الحقيقي يُطلق ServingFilament فتُسجَّل مكوّنات اللوحة.
        $this->get($resource::getUrl('index', panel: 'admin'));

        return [$organizationId, $actor];
    }
}
