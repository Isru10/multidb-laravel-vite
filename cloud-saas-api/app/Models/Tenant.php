<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Illuminate\Support\Facades\Crypt;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $fillable = [
        'id',
        'data',
        'tenancy_db_host',
        'tenancy_db_port',
        'tenancy_db_username',
        'tenancy_db_password', // ENCRYPTED
        'tenancy_db_name',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * Relationship to Users in the central database
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'tenant_user')->withTimestamps();
    }

    /**
     * Get decrypted database password
     */
    public function getDecryptedPassword(): string
    {
        if (! filled($this->tenancy_db_password)) {
            return '';
        }

        try {
            return Crypt::decryptString($this->tenancy_db_password);
        } catch (\Throwable $throwable) {
            // Backward compatibility: older tenant rows may have been stored as plain text
            // or an already assembled connection fragment. Use the raw value so tenant
            // migrations can still run and existing tenants remain accessible.
            return (string) $this->tenancy_db_password;
        }
    }

    /**
     * Extract the Neon endpoint id from the host when using pooler hosts.
     */
    public function getNeonEndpointId(): ?string
    {
        if (! filled($this->tenancy_db_host)) {
            return null;
        }

        if (preg_match('/^([^.]+?)-pooler\./', $this->tenancy_db_host, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get database connection config for this tenant
     */
    public function getDatabaseConfig(): array
    {
        $config = [
            'driver' => 'tenant_pgsql',
            'host' => $this->tenancy_db_host,
            'port' => $this->tenancy_db_port ?? 5432,
            'database' => $this->tenancy_db_name,
            'username' => $this->tenancy_db_username,
            'password' => $this->getDecryptedPassword(),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'dsn_options' => [
                'sslmode' => 'require',
            ],
        ];

        $endpointId = $this->getNeonEndpointId();

        if ($endpointId) {
            $config['dsn_options']['options'] = 'endpoint=' . $endpointId;
        }

        return $config;
    }
}
