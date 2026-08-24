<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * الصنف الأساسي لكل اختبارات المستودع — بما فيها اختبارات الموديولات
 * تحت modules/<Name>/tests/.
 */
abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $this->assertRawIsolatedEnvironment();

        /** @var Application $app */
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $this->assertBootstrappedIsolatedEnvironment($app);

        return $app;
    }

    /**
     * This guard runs before Laravel is bootstrapped and, crucially, before
     * RefreshDatabase can execute migrate:fresh.
     */
    private function assertRawIsolatedEnvironment(): void
    {
        $environment = $this->rawEnvironmentValue('APP_ENV');
        $connection = $this->rawEnvironmentValue('DB_CONNECTION');
        $database = $this->rawEnvironmentValue('DB_DATABASE');
        $token = $this->rawEnvironmentValue('TEST_RUN_TOKEN');
        $filesystem = $this->rawEnvironmentValue('FILESYSTEM_DISK');
        $databaseUrl = $this->rawEnvironmentValue('DB_URL');

        if ($environment !== 'testing') {
            throw new \RuntimeException('Safety Guard: tests require APP_ENV=testing.');
        }

        if ($connection !== 'pgsql' || $databaseUrl !== '' || !$this->isGeneratedDatabase($database, $token)) {
            throw new \RuntimeException(
                "Safety Guard: use scripts/test-isolated.php; refusing database '{$database}'.",
            );
        }

        if ($filesystem !== 'test_isolated') {
            throw new \RuntimeException('Safety Guard: isolated filesystem must be selected before bootstrap.');
        }
    }

    private function assertBootstrappedIsolatedEnvironment(Application $app): void
    {
        $token = $this->rawEnvironmentValue('TEST_RUN_TOKEN');
        $database = (string) $app->make('config')->get('database.connections.pgsql.database');
        $filesystem = (string) $app->make('config')->get('filesystems.default');

        if ((string) $app->environment() !== 'testing' || !$this->isGeneratedDatabase($database, $token)) {
            throw new \RuntimeException('Safety Guard: bootstrapped application escaped the generated test database.');
        }

        if ($filesystem !== 'test_isolated') {
            throw new \RuntimeException('Safety Guard: tests require the isolated filesystem disk.');
        }
    }

    private function isGeneratedDatabase(string $database, string $token): bool
    {
        return preg_match('/\A[a-z0-9][a-z0-9_]{11,43}\z/', $token) === 1
            && $database === 'eschool_testing_'.$token
            && $database !== 'eschool_testing';
    }

    private function rawEnvironmentValue(string $key): string
    {
        $value = getenv($key);

        if ($value === false) {
            $value = $_SERVER[$key] ?? $_ENV[$key] ?? '';
        }

        return is_string($value) ? $value : '';
    }
}
