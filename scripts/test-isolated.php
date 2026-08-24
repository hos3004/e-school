<?php

declare(strict_types=1);

namespace ESchool\Scripts;

use Dotenv\Dotenv;
use FilesystemIterator;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

require dirname(__DIR__).'/vendor/autoload.php';

final class IsolatedTestRunner
{
    private const DATABASE_PREFIX = 'eschool_testing_';

    private readonly string $root;

    /** @var array<string, string> */
    private array $environment;

    public function __construct()
    {
        $this->root = dirname(__DIR__);
        Dotenv::createImmutable($this->root)->safeLoad();

        $rawProcessEnvironment = getenv();
        if (!is_array($rawProcessEnvironment)) {
            $rawProcessEnvironment = [];
        }

        /** @var array<string, string> $processEnvironment */
        $processEnvironment = array_filter(
            $rawProcessEnvironment,
            static fn (mixed $value, mixed $key): bool => is_string($key) && is_string($value),
            ARRAY_FILTER_USE_BOTH,
        );

        /** @var array<string, string> $fileEnvironment */
        $fileEnvironment = array_filter(
            $_ENV,
            static fn (mixed $value, mixed $key): bool => is_string($key) && is_string($value),
            ARRAY_FILTER_USE_BOTH,
        );

        $this->environment = array_replace($fileEnvironment, $processEnvironment);
    }

    /** @param list<string> $arguments */
    public function execute(array $arguments): int
    {
        if (($arguments[0] ?? null) === '--cleanup') {
            return $this->cleanupCommand($arguments[1] ?? '');
        }

        if (($arguments[0] ?? null) === '--parallel-proof') {
            return $this->parallelProof(array_slice($arguments, 1));
        }

        return $this->runPest($arguments);
    }

    /** @param list<string> $pestArguments */
    private function runPest(array $pestArguments): int
    {
        $token = $this->newToken();
        $database = self::DATABASE_PREFIX.$token;
        $storage = $this->storagePath($token);

        $this->createDatabase($database);
        try {
            $this->createStorageDirectories($storage);

            fwrite(STDOUT, "Isolated test database: {$database}\n");
            fwrite(STDOUT, "Isolated storage: {$storage}\n");

            return $this->runProcess(
                [
                    PHP_BINARY,
                    $this->root.'/vendor/bin/pest',
                    '--cache-directory='.$storage.'/phpunit-cache',
                    '--do-not-record-test-run-history',
                    ...$pestArguments,
                ],
                $this->testEnvironment($token, $database, $storage),
            );
        } finally {
            try {
                $this->dropDatabase($database);
            } finally {
                $this->removeStorage($token);
            }
        }
    }

    /** @param list<string> $pestArguments */
    private function parallelProof(array $pestArguments): int
    {
        if ($pestArguments === []) {
            $pestArguments = ['tests/Feature/Infrastructure/IsolatedTestEnvironmentTest.php'];
        }

        $processes = [];

        foreach (['parallel_a', 'parallel_b'] as $agent) {
            $environment = $this->environment;
            $environment['TEST_AGENT_ID'] = $agent;
            $processes[$agent] = proc_open(
                [PHP_BINARY, __FILE__, ...$pestArguments],
                [STDIN, STDOUT, STDERR],
                $pipes,
                $this->root,
                $environment,
            );

            if (!is_resource($processes[$agent])) {
                throw new RuntimeException("Unable to start {$agent} isolation proof.");
            }
        }

        $exitCode = 0;
        foreach ($processes as $agent => $process) {
            $status = proc_close($process);
            if ($status !== 0) {
                fwrite(STDERR, "{$agent} failed with exit code {$status}.\n");
                $exitCode = 1;
            }
        }

        return $exitCode;
    }

    private function cleanupCommand(string $database): int
    {
        $token = $this->tokenFromDatabase($database);
        $this->dropDatabase($database);
        $this->removeStorage($token);
        fwrite(STDOUT, "Removed isolated resources for {$database}.\n");

        return 0;
    }

    private function createDatabase(string $database): void
    {
        $pdo = $this->maintenanceConnection();
        $exists = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = :database');
        $exists->execute(['database' => $database]);

        if ($exists->fetchColumn() !== false) {
            throw new RuntimeException("Generated test database already exists: {$database}");
        }

        $pdo->exec('CREATE DATABASE '.$this->quotedIdentifier($database).' TEMPLATE template0 ENCODING \'UTF8\'');
    }

    private function dropDatabase(string $database): void
    {
        $this->tokenFromDatabase($database);
        $pdo = $this->maintenanceConnection();
        $terminate = $pdo->prepare(
            'SELECT pg_terminate_backend(pid) FROM pg_stat_activity '
            .'WHERE datname = :database AND pid <> pg_backend_pid()',
        );
        $terminate->execute(['database' => $database]);
        $pdo->exec('DROP DATABASE IF EXISTS '.$this->quotedIdentifier($database));
    }

    private function maintenanceConnection(): PDO
    {
        $host = $this->environmentValue('DB_HOST', 'postgres');
        $port = $this->environmentValue('DB_PORT', '5432');
        $username = $this->environmentValue('DB_USERNAME', 'eschool');
        $password = $this->environmentValue('DB_PASSWORD', '');
        $maintenanceDatabase = $this->environmentValue('TEST_DB_MAINTENANCE_DATABASE', 'postgres');

        if (!preg_match('/\A[a-zA-Z0-9_-]+\z/', $maintenanceDatabase)) {
            throw new RuntimeException('Unsafe PostgreSQL maintenance database name.');
        }

        return new PDO(
            "pgsql:host={$host};port={$port};dbname={$maintenanceDatabase}",
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $environment
     */
    private function runProcess(array $command, array $environment): int
    {
        $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $this->root, $environment);

        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start Pest.');
        }

        return proc_close($process);
    }

    /** @return array<string, string> */
    private function testEnvironment(string $token, string $database, string $storage): array
    {
        return array_replace($this->environment, [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'pgsql',
            'DB_DATABASE' => $database,
            'DB_URL' => '',
            'TEST_RUN_TOKEN' => $token,
            'CACHE_STORE' => 'array',
            'CACHE_PREFIX' => $database.':cache:',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
            'BROADCAST_CONNECTION' => 'null',
            'FILESYSTEM_DISK' => 'test_isolated',
            'LOG_CHANNEL' => 'stderr',
            'LOG_LEVEL' => 'error',
            'REDIS_PREFIX' => $database.':redis:',
            'HORIZON_PREFIX' => $database.':horizon:',
            'CLASSROOM_PROVIDER' => 'null',
            'TELESCOPE_ENABLED' => 'false',
            /*
             * ADR-017: احتساب أجر المعلم داخل النطاق، فتُختبر مساراته فعليًا.
             * ما يبقى مطفأً هو الدفع لا الاحتساب: فوترة الطلاب وصرف المستحقات.
             */
            'MODULE_PAYROLL_ENABLED' => 'true',
            'FEATURE_PAYROLL' => 'true',
            'FEATURE_STUDENT_BILLING' => 'false',
            'FEATURE_TEACHER_PAYOUTS' => 'false',
            'VIEW_COMPILED_PATH' => $storage.'/views',
            'APP_SERVICES_CACHE' => $storage.'/cache/services.php',
            'APP_PACKAGES_CACHE' => $storage.'/cache/packages.php',
            'APP_CONFIG_CACHE' => $storage.'/cache/config.php',
            'APP_ROUTES_CACHE' => $storage.'/cache/routes.php',
            'APP_EVENTS_CACHE' => $storage.'/cache/events.php',
        ]);
    }

    private function newToken(): string
    {
        $agent = strtolower($this->environmentValue('TEST_AGENT_ID', 'local'));
        $agent = trim((string) preg_replace('/[^a-z0-9]+/', '_', $agent), '_');
        $agent = substr($agent !== '' ? $agent : 'local', 0, 14);
        $timestamp = gmdate('Ymd_His');

        return $agent.'_'.$timestamp.'_'.bin2hex(random_bytes(4));
    }

    private function tokenFromDatabase(string $database): string
    {
        if (!preg_match('/\Aeschool_testing_([a-z0-9][a-z0-9_]{11,43})\z/', $database, $matches)) {
            throw new RuntimeException("Refusing cleanup for non-generated database '{$database}'.");
        }

        return $matches[1];
    }

    private function quotedIdentifier(string $identifier): string
    {
        $this->tokenFromDatabase($identifier);

        return '"'.$identifier.'"';
    }

    private function storagePath(string $token): string
    {
        if (!preg_match('/\A[a-z0-9][a-z0-9_]{11,43}\z/', $token)) {
            throw new RuntimeException('Unsafe test storage token.');
        }

        return $this->root.'/storage/framework/testing/'.$token;
    }

    private function createStorageDirectories(string $storage): void
    {
        foreach ([$storage, $storage.'/views', $storage.'/cache'] as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException("Unable to create isolated directory {$directory}.");
            }
        }
    }

    private function removeStorage(string $token): void
    {
        $path = $this->storagePath($token);

        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isLink() || !$item->isDir()) {
                unlink($item->getPathname());
            } else {
                rmdir($item->getPathname());
            }
        }

        rmdir($path);
    }

    private function environmentValue(string $key, string $default): string
    {
        $value = $this->environment[$key] ?? $default;

        return $value !== '' ? $value : $default;
    }
}

try {
    exit((new IsolatedTestRunner)->execute(array_slice($argv, 1)));
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Isolated test runner failed: '.$throwable->getMessage()."\n");
    exit(1);
}
