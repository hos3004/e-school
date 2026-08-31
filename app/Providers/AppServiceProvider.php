<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Identity\OrganizationUsernamePrefixAdapter;
use App\Listeners\SyncClassroomRecordings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Modules\Identity\Domain\Contracts\OrganizationUsernamePrefixProvider;
use Modules\VirtualClassroom\Domain\Events\ClassroomEnded;
use Shared\Support\DatabaseTransaction;
use Shared\Support\Transaction;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Transaction::class, DatabaseTransaction::class);
        $this->app->bind(OrganizationUsernamePrefixProvider::class, OrganizationUsernamePrefixAdapter::class);
    }

    public function boot(): void
    {
        Event::listen(ClassroomEnded::class, SyncClassroomRecordings::class);

        // التواريخ دائمًا UTC داخليًا — العرض بتوقيت المستخدم فقط.
        Date::use(CarbonImmutable::class);

        // منع الوصول الكسول للعلاقات وتمرير خصائص غير موجودة خارج الإنتاج.
        Model::shouldBeStrict(!$this->app->isProduction());
        Model::unguard(false);

        // تحذير من الاستعلامات البطيئة في التطوير.
        if (!$this->app->isProduction()) {
            DB::whenQueryingForLongerThan(500, function ($connection, $event): void {
                logger()->warning('Slow query detected', [
                    'sql' => $event->sql,
                    'time_ms' => $event->time,
                ]);
            });
        }

        Password::defaults(fn () => Password::min(10)->letters()->numbers()->uncompromised());
    }
}
