<?php

declare(strict_types=1);

/**
 * أداة قياس صفحات ممثلة قبل/بعد — تُنفَّذ داخل الحاوية فقط:
 *
 *   docker compose exec -T app php scripts/bench-pages.php
 *
 * تقيس لكل مسار: إحماء واحد ثم ثلاث محاولات (زمن الخادم بالمللي ثانية
 * وعدد استعلامات SQL لكل محاولة). المسارات المحمية تُفتح بحساب تجريبي
 * عبر actingAs دون تعديل أي بيانات (GET فقط).
 */

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Domain\Models\User;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';

$kernel = $app->make(HttpKernel::class);
$kernel->bootstrap();

$admin = DB::table('users')->where('email', 'admin@demo.local')->value('id');
$student = DB::table('users')->where('email', 'student1@demo.local')->value('id');
$teacher = DB::table('users')->where('email', 'demo.teacher1@demo.local')->value('id');

$authProvider = static function () use ($app): User {
    return $app->make(User::class);
};

$targets = [
    ['GET /up', 'GET', 'up', null],
    ['GET /admin/login', 'GET', 'admin/login', null],
    ['GET /login', 'GET', 'login', null],
    ['GET /admin (dashboard)', 'GET', 'admin', $admin],
    ['GET /admin/students', 'GET', 'admin/students', $admin],
    ['GET /admin/staff-profiles', 'GET', 'admin/staff-profiles', $admin],
    ['GET /admin/users', 'GET', 'admin/users', $admin],
    ['GET /student (portal)', 'GET', 'student', $student],
    ['GET /teacher (portal)', 'GET', 'teacher', $teacher],
];

fwrite(STDOUT, sprintf(
    "%s | %s | %s | %s\n",
    str_pad('route', 26),
    str_pad('status', 6),
    str_pad('queries', 7),
    'server_ms x3',
));

foreach ($targets as [$label, $method, $uri, $userId]) {
    $times = [];
    $queryCount = 0;
    $status = 0;

    foreach ([0, 1, 2, 3] as $attempt) {
        $request = Request::create('/'.$uri, $method);
        $request->setLaravelSession($app['session.store']);

        if ($userId !== null) {
            $user = $authProvider()->findOrFail($userId);
            auth()->setUser($user);

            // جلسة صالحة لوسيط AuthenticateSession كما في الدخول الحقيقي.
            $request->session()->put('password_hash_web', $user->getAuthPassword());
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $start = hrtime(true);
        $response = $kernel->handle($request);
        $elapsed = (hrtime(true) - $start) / 1e6;

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        if ($attempt === 0) {
            $status = $response->getStatusCode();

            continue;
        }

        $times[] = $elapsed;
        $queryCount = max($queryCount, count($queries));
    }

    sort($times);
    $median = $times[intdiv(count($times), 2)] ?? 0.0;
    $all = implode('/', array_map(static fn (float $t): string => number_format($t, 0), $times));

    fwrite(STDOUT, sprintf(
        "%s | %d | %d | %s (median %.0f)\n",
        str_pad($label, 26),
        $status,
        $queryCount,
        $all,
        $median,
    ));
}
