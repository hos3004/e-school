<?php

declare(strict_types=1);

namespace Shared\Support;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * غلاف معاملة قاعدة البيانات.
 *
 * الإجراءات تعتمد على هذه الواجهة بدل استدعاء DB::transaction مباشرة،
 * حتى يمكن استبدالها في الاختبارات بتنفيذ لا يفتح معاملة حقيقية.
 */
interface Transaction
{
    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(Closure $callback, int $attempts = 1): mixed;
}
