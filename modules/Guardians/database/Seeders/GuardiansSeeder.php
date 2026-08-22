<?php

declare(strict_types=1);

namespace Modules\Guardians\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Guardians\Application\Actions\LinkStudentToGuardian;
use Modules\Guardians\Domain\Enums\ContactChannel;
use Modules\Guardians\Domain\Enums\GuardianRelationship;
use Modules\Guardians\Domain\Models\GuardianLink;
use Modules\Guardians\Domain\Models\GuardianProfile;

/**
 * بيانات تجريبية معقولة: ثلاثة أوصياء، لكل منهم روابط بحالات مختلفة.
 *
 * المعرّفات الخارجية (user_id / student_profile_id) ULIDs مستقلة —
 * موديولات أخرى ستربط حساباتها الحقيقية لاحقًا.
 */
final class GuardiansSeeder extends Seeder
{
    public function run(): void
    {
        $organizationId = (string) DB::table('organizations')->orderBy('created_at')->value('id');

        if ($organizationId === '') {
            $this->command?->warn('GuardiansSeeder: لا توجد مؤسسة — شغّل OrganizationSeeder أولًا.');

            return;
        }

        $father = GuardianProfile::query()->create([
            'organization_id' => $organizationId,
            'user_id' => $this->makeUser($organizationId),
            'national_id_last4' => '4821',
            'occupation' => 'engineer',
            'preferred_contact_channel' => ContactChannel::WhatsApp,
        ]);

        $mother = GuardianProfile::query()->create([
            'organization_id' => $organizationId,
            'user_id' => $this->makeUser($organizationId),
            'national_id_last4' => '7734',
            'occupation' => 'teacher',
            'preferred_contact_channel' => ContactChannel::PhoneCall,
        ]);

        $uncle = GuardianProfile::query()->create([
            'organization_id' => $organizationId,
            'user_id' => $this->makeUser($organizationId),
            'national_id_last4' => null,
            'occupation' => 'merchant',
            'preferred_contact_channel' => ContactChannel::InApp,
        ]);

        /** @var LinkStudentToGuardian $linkAction */
        $linkAction = app(LinkStudentToGuardian::class);

        $students = DB::table('student_profiles')->orderBy('created_at')->limit(3)->pluck('id')->all();

        if (count($students) < 3) {
            $this->command?->warn('GuardiansSeeder: عدد الطلاب أقل من ثلاثة — شغّل StudentsSeeder أولًا.');

            return;
        }

        [$sonA, $sonB, $nephew] = array_map(static fn ($id): string => (string) $id, $students);

        // الأب: واصٍ أساسي موثّق على ابنه، ورابط ثانٍ غير موثّق.
        $linkAction->execute($father->id, $sonA, [
            'relationship' => GuardianRelationship::Father,
            'is_primary' => true,
            'can_act_for' => true,
        ]);
        GuardianLink::query()
            ->forGuardian($father->id)
            ->forStudent($sonA)
            ->first()?->forceFill(['verified_at' => now('UTC')])->save();

        $linkAction->execute($father->id, $sonB, [
            'relationship' => GuardianRelationship::Father,
            'is_primary' => true,
            'can_act_for' => false,
        ]);

        // الأم: واصٍ ثانٍ موثّق على الابن الأول.
        $linkAction->execute($mother->id, $sonA, [
            'relationship' => GuardianRelationship::Mother,
            'can_act_for' => true,
        ]);
        GuardianLink::query()
            ->forGuardian($mother->id)
            ->forStudent($sonA)
            ->where('is_primary', false)
            ->first()?->forceFill(['verified_at' => now('UTC')])->save();

        // العم: واصٍ بلا وساطة بعد — بانتظار التوثيق.
        $linkAction->execute($uncle->id, $nephew, [
            'relationship' => GuardianRelationship::Uncle,
            'can_act_for' => false,
        ]);
    }

    /**
     * ينشئ حساب مستخدم حقيقيًا لولي الأمر — المفتاح الأجنبي يتطلب صفًا موجودًا.
     */
    private function makeUser(string $organizationId): string
    {
        $id = (string) Str::ulid();
        $suffix = substr($id, -6);

        DB::table('users')->insert([
            'id' => $id,
            'organization_id' => $organizationId,
            'name' => 'Guardian '.$suffix,
            'email' => 'guardian.'.strtolower($suffix).'@demo.local',
            'password' => Hash::make(Str::password(16)),
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
