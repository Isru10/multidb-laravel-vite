<?php

namespace App\Tenancy;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager;
use PDO;
use Exception;
use Illuminate\Support\Facades\Log;

class CustomPostgresManager extends PostgreSQLDatabaseManager
{
    /**
     * Create database for tenant (verify connection to Neon)
     *
     * For Neon, we don't actually create the database here.
     * The database should be pre-created on Neon.
     * We just verify that we can connect to it.
     */
    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        try {
            // Test if we can connect to the database
            $pdo = $this->getPDOConnection($tenant);

            Log::info("Successfully verified database connection for tenant: {$tenant->id}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to verify database for tenant: {$tenant->id}", [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            throw $e;
        }
    }

    /**
     * Get a PDO connection for the tenant using their custom credentials
     */
    protected function getPDOConnection(TenantWithDatabase $tenant): PDO
    {
        // Get the tenant's specific database configuration
        $config = $tenant->getDatabaseConfig();

        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $config['host'],
            $config['port'],
            $config['database']
        );

        foreach (($config['dsn_options'] ?? []) as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $dsn .= ';' . $key . '=' . $value;
        }

        return new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    /**
     * Delete database for tenant
     *
     * WARNING: This is dangerous! For MVP, we skip actual deletion
     * to prevent accidental data loss. Implement with care in production.
     */
    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        // For now, just log the deletion request without actually doing it
        Log::warning("Delete database requested for tenant: {$tenant->id}. Skipping for safety.");

        // In production, you might:
        // 1. Require approval/confirmation
        // 2. Create backup first
        // 3. Then drop the database
        // 4. Call Neon API to remove the database

        return true;
    }
}
