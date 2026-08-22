<?php

declare(strict_types=1);

namespace Shared\Support;

use Closure;
use Illuminate\Database\ConnectionInterface;

/**
 * التنفيذ الافتراضي: معاملة حقيقية على اتصال قاعدة البيانات.
 */
final readonly class DatabaseTransaction implements Transaction
{
    public function __construct(private ConnectionInterface $connection) {}

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(Closure $callback, int $attempts = 1): mixed
    {
        return $this->connection->transaction($callback, $attempts);
    }
}
