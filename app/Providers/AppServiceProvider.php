<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Identity\OrganizationUsernamePrefixAdapter;
use App\Listeners\ApplyAutomaticDisciplineFreeze;
use App\Listeners\FinalizeClassroomAttendance;
use App\Listeners\StartSessionOnTeacherJoin;
use App\Listeners\SyncClassroomRecordings;
use App\Listeners\TrackClassroomParticipantAttendance;
use App\Support\QueryPerformance;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Modules\Discipline\Domain\Events\DisciplineActionApplied;
use Modules\Identity\Domain\Contracts\OrganizationUsernamePrefixProvider;
use Modules\VirtualClassroom\Domain\Events\ClassroomEnded;
use Modules\VirtualClassroom\Domain\Events\ClassroomParticipantJoined;
use Modules\VirtualClassroom\Domain\Events\ClassroomParticipantLeft;
use Shared\Support\DatabaseTransaction;
use Shared\Support\Transaction;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(QueryPerformance::class);
        $this->app->bind(Transaction::class, DatabaseTransaction::class);
        $this->app->bind(OrganizationUsernamePrefixProvider::class, OrganizationUsernamePrefixAdapter::class);
    }

    public function boot(): void
    {
        Event::listen(ClassroomEnded::class, SyncClassroomRecordings::class);

        Event::listen(ClassroomParticipantJoined::class, TrackClassroomParticipantAttendance::class);
        Event::listen(ClassroomParticipantJoined::class, StartSessionOnTeacherJoin::class);
        Event::listen(ClassroomParticipantLeft::class, TrackClassroomParticipantAttendance::class);
        Event::listen(ClassroomEnded::class, FinalizeClassroomAttendance::class);
        Event::listen(DisciplineActionApplied::class, ApplyAutomaticDisciplineFreeze::class);
        // التواريخ دائمًا UTC داخليًا — العرض بتوقيت المستخدم فقط.
        Date::use(CarbonImmutable::class);

        // منع الوصول الكسول للعلاقات وتمرير خصائص غير موجودة خارج الإنتاج.
        Model::shouldBeStrict(!$this->app->isProduction());
        Model::unguard(false);

        if ((bool) config('performance.enabled') && !$this->app->runningInConsole()) {
            DB::listen(static function (QueryExecuted $query): void {
                app(QueryPerformance::class)->record($query);
            });
        }

        Password::defaults(fn () => Password::min(10)->letters()->numbers()->uncompromised());
    }
}
