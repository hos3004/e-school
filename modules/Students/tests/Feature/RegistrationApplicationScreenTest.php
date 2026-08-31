<?php

declare(strict_types=1);

namespace Modules\Students\Tests\Feature;

use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Students\Domain\Enums\RegistrationQuestionType;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\RegistrationQuestion;
use Modules\Students\Presentation\Filament\Resources\RegistrationApplicationResource;
use Shared\Testing\Fixtures;
use Tests\TestCase;

/**
 * الشاشة نفسها: هل تُبنى الفلاتر والإجراء الجماعي فعلًا وتُخفى عن غير المخوَّل؟
 *
 * هذه الاختبارات تحرس التوصيل — أن الزر متصل وأن الفلتر الديناميكي يظهر ولا
 * يظهر غيره — وهو ما لا يكشفه اختبار الخدمات وحده.
 */
final class RegistrationApplicationScreenTest extends TestCase
{
    use RefreshDatabase;

    private string $organizationId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GeographySeeder::class);
        $this->organizationId = Fixtures::organizationId();
    }

    public function test_the_table_exposes_the_expected_static_filters(): void
    {
        $this->actingAsAdmin();

        $names = $this->filterNames();

        foreach (['registration_form_id', 'status', 'gender', 'country_id', 'region_id', 'preferred_language', 'age_range', 'registered_at'] as $expected) {
            $this->assertContains($expected, $names, "الفلتر «{$expected}» غير معروض.");
        }
    }

    public function test_only_questions_marked_filterable_become_filters(): void
    {
        $this->actingAsAdmin();

        $allowed = $this->question(RegistrationQuestionType::Select, true);
        $hidden = $this->question(RegistrationQuestionType::Select, false);

        $names = $this->filterNames();

        $this->assertContains('question_'.$allowed->getKey(), $names);
        $this->assertNotContains('question_'.$hidden->getKey(), $names);
    }

    public function test_the_bulk_placement_action_is_registered_on_the_table(): void
    {
        $this->actingAsAdmin();

        $table = RegistrationApplicationResource::table(
            Table::make($this->tableLivewireStub()),
        );

        $names = array_map(
            static fn (object $action): string => $action->getName(),
            $table->getToolbarActions(),
        );

        $this->assertContains('assignToGroup', $names);
    }

    public function test_a_user_without_the_abilities_cannot_open_the_bulk_action(): void
    {
        $application = $this->application();

        // مستخدم بلا أي صلاحية من الثلاث.
        Gate::before(static fn (): ?bool => null);
        $viewer = $this->user();
        $this->actingAs($viewer);

        $this->assertFalse($viewer->can('assignAny', RegistrationApplication::class));
        $this->assertFalse($viewer->can('assign', $application));
    }

    public function test_the_query_is_scoped_to_the_current_organization(): void
    {
        $this->actingAsAdmin();

        $this->application();

        $sql = RegistrationApplicationResource::getEloquentQuery()->toSql();
        $bindings = RegistrationApplicationResource::getEloquentQuery()->getBindings();

        $this->assertStringContainsString('organization_id', $sql);
        $this->assertContains($this->organizationId, $bindings);
    }

    // ── مساعدات ─────────────────────────────────────────────────────────

    /** @return list<string> */
    private function filterNames(): array
    {
        $table = RegistrationApplicationResource::table(
            Table::make($this->tableLivewireStub()),
        );

        return array_map(
            static fn (object $filter): string => $filter->getName(),
            array_values($table->getFilters()),
        );
    }

    private function actingAsAdmin(): User
    {
        $user = $this->user();
        // كل الصلاحيات — الشاشة تُبنى كما يراها مستخدم مخوَّل بالكامل.
        Gate::before(static fn (): bool => true);
        $this->actingAs($user);

        return $user;
    }

    private function user(): User
    {
        return User::query()->findOrFail(Fixtures::userId());
    }

    private function question(RegistrationQuestionType $type, bool $filterable): RegistrationQuestion
    {
        return RegistrationQuestion::query()->create([
            'organization_id' => $this->organizationId,
            'question' => ['ar' => 'سؤال', 'en' => 'Question'],
            'type' => $type,
            'options' => ['أ', 'ب'],
            'is_required' => false,
            'is_active' => true,
            'is_filterable' => $filterable,
            'sort_order' => 0,
        ]);
    }

    private function application(): RegistrationApplication
    {
        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);
        $country = $geography->findCountryByIso2('EG');

        return RegistrationApplication::query()->create([
            'organization_id' => $this->organizationId,
            'user_id' => Fixtures::userId(),
            'status' => RegistrationStatus::Submitted,
            'full_name' => 'طالب',
            'date_of_birth' => '2010-01-01',
            'gender' => 'male',
            'country_id' => $country->id,
            'region_id' => $geography->regionsOf($country->id)[0]->id,
        ]);
    }

    /** كائن Livewire أدنى ما يقبله Filament لبناء الجدول خارج الصفحة. */
    private function tableLivewireStub(): HasTable
    {
        return new class extends Component implements HasTable
        {
            use InteractsWithTable;

            public function getTable(): Table
            {
                return RegistrationApplicationResource::table(Table::make($this));
            }

            public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
            {
                return null;
            }
        };
    }
}
