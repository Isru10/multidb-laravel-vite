<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Database\Models\Domain;

class TenantController extends Controller
{
    private function tenantBaseDomain(): string
    {
        return env('TENANT_BASE_DOMAIN', 'lvh.me');
    }

    private function composeTenantDomain(string $subdomain): string
    {
        return $subdomain . '.' . $this->tenantBaseDomain();
    }

    private function parsePostgresConnectionString(string $connectionString): array
    {
        $parts = parse_url($connectionString);

        if ($parts === false) {
            throw new \InvalidArgumentException('Invalid PostgreSQL connection string.');
        }

        parse_str($parts['query'] ?? '', $query);

        return [
            'host' => $parts['host'] ?? '',
            'port' => (int) ($parts['port'] ?? 5432),
            'database' => ltrim($parts['path'] ?? '', '/'),
            'username' => isset($parts['user']) ? urldecode($parts['user']) : '',
            'password' => isset($parts['pass']) ? urldecode($parts['pass']) : '',
            'query' => $query,
        ];
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'clinic_name' => 'required|string|max:255',
            'clinic_id' => 'required|string|regex:/^[a-z0-9-]+$/',
            'subdomain' => 'required|string|regex:/^[a-z0-9-]+$/',
            'admin_email' => 'required|email',
            'admin_name' => 'nullable|string|max:255',
            'connection_string' => 'required|string',
        ]);

        $tenantDomain = $this->composeTenantDomain($validated['subdomain']);
        $connection = $this->parsePostgresConnectionString($validated['connection_string']);

        $existingDomain = Domain::query()->where('domain', $tenantDomain)->first();

        if ($existingDomain && $existingDomain->tenant_id !== $validated['clinic_id']) {
            return response()->json([
                'error' => 'This domain is already assigned to another tenant.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $tenant = Tenant::updateOrCreate(
                ['id' => $validated['clinic_id']],
                [
                    'data' => [
                        'name' => $validated['clinic_name'],
                        'subdomain' => $validated['subdomain'],
                        'domain' => $tenantDomain,
                    ],
                    'tenancy_db_host' => $connection['host'],
                    'tenancy_db_port' => $connection['port'],
                    'tenancy_db_username' => $connection['username'],
                    'tenancy_db_password' => Crypt::encryptString($connection['password']),
                    'tenancy_db_name' => $connection['database'],
                ]
            );

            Domain::updateOrCreate(
                ['domain' => $tenantDomain],
                ['tenant_id' => $tenant->id]
            );

            $adminUser = User::firstOrCreate(
                ['email' => $validated['admin_email']],
                [
                    'name' => $validated['admin_name'] ?? ('Admin - ' . $validated['clinic_name']),
                    'provider_name' => 'google',
                    'is_super_admin' => false,
                ]
            );

            $tenant->users()->syncWithoutDetaching([$adminUser->id]);

            DB::commit();

            event(new \Stancl\Tenancy\Events\TenantCreated($tenant));

            tenancy()->initialize($tenant);
            Role::findOrCreate('admin', 'web');
            Role::findOrCreate('member', 'web');
            if (! $adminUser->hasRole('admin')) {
                $adminUser->assignRole('admin');
            }
            tenancy()->end();

            Log::info('New tenant created successfully', [
                'tenant_id' => $tenant->id,
                'clinic_name' => $validated['clinic_name'],
                'admin_email' => $validated['admin_email'],
                'domain' => $tenantDomain,
            ]);

            return response()->json([
                'message' => 'Clinic successfully created and initialized!',
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->data['name'],
                    'subdomain' => $validated['subdomain'],
                    'domain' => $tenantDomain,
                    'tenant_url' => 'http://' . $tenantDomain . ':3000',
                    'admin_email' => $adminUser->email,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create tenant', [
                'error' => $e->getMessage(),
                'clinic_id' => $validated['clinic_id'] ?? 'unknown',
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to create clinic: ' . $e->getMessage(),
                'debug' => env('APP_DEBUG') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    public function index()
    {
        try {
            $tenants = Tenant::with('domains', 'users')
                ->get()
                ->map(function ($tenant) {
                    return [
                        'id' => $tenant->id,
                        'name' => $tenant->data['name'] ?? 'Unnamed',
                        'domain' => $tenant->domains()->first()?->domain,
                        'user_count' => $tenant->users()->count(),
                        'created_at' => $tenant->created_at,
                    ];
                });

            return response()->json($tenants);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve tenants', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to retrieve tenants',
            ], 500);
        }
    }

    public function show(string $tenantId)
    {
        try {
            $tenant = Tenant::with('domains', 'users')->findOrFail($tenantId);

            return response()->json([
                'id' => $tenant->id,
                'name' => $tenant->data['name'] ?? 'Unnamed',
                'domain' => $tenant->domains()->first()?->domain,
                'users' => $tenant->users()->get(['id', 'name', 'email']),
                'created_at' => $tenant->created_at,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Tenant not found or error retrieving tenant',
            ], 404);
        }
    }
}
