<?php

namespace App\Tenancy;

use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

class DynamicConnectionBootstrapper implements TenancyBootstrapper
{
    /**
     * Store the tenant temporarily so we can log its ID during reversion.
     */
    protected ?Tenant $tenant = null;

    protected static bool $driverRegistered = false;

    private function buildDsn(array $config): string
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $config['host'],
            $config['port'] ?? 5432,
            $config['database']
        );

        foreach (($config['dsn_options'] ?? []) as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $dsn .= ';' . $key . '=' . $value;
        }

        return $dsn;
    }

    private function registerDriverIfNeeded(): void
    {
        if (self::$driverRegistered) {
            return;
        }

        DB::extend('tenant_pgsql', function (array $config, string $name) {
            $pdo = new PDO(
                $this->buildDsn($config),
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            return new PostgresConnection($pdo, $config['database'], $config['prefix'] ?? '', $config);
        });

        self::$driverRegistered = true;
    }

    /**
     * Bootstrap tenancy: dynamically create database connection for the tenant
     *
     * This is called when a request is identified as being for a specific tenant.
     * It reads the tenant's encrypted database credentials and configures Laravel's
     * database connections to point to that tenant's Neon database.
     */
    public function bootstrap(Tenant $tenant): void
    {
        $this->tenant = $tenant;

        try {
            $this->registerDriverIfNeeded();

            // Get the tenant's database configuration
            $config = $tenant->getDatabaseConfig();

            // Register this connection dynamically in Laravel's database config
            Config::set('database.connections.tenant', $config);

            Log::debug("Bootstrapped tenancy for tenant: {$tenant->id}", [
                'host' => $config['host'],
                'database' => $config['database'],
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to bootstrap tenancy for tenant: {$tenant->id}", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Revert tenancy: clean up the tenant connection
     *
     * This is called when we're exiting tenant context (going back to central DB).
     */
    public function revert(): void
    {
        try {
            DB::purge('tenant');

            if ($this->tenant) {
                Log::debug("Reverted tenancy for tenant: {$this->tenant->id}");
                $this->tenant = null;
            }

        } catch (\Exception $e) {
            Log::error('Failed to revert tenancy.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
