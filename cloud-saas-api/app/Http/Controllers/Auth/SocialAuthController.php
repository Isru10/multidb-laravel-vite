<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Database\Models\Domain;

class SocialAuthController extends Controller
{
    public function redirect(string $provider)
    {
        $driver = Socialite::driver($provider);

        if (method_exists($driver, 'stateless')) {
            $driver = $driver->stateless();
        }

        return response()->json([
            'url' => $driver->redirect()->getTargetUrl(),
        ]);
    }

    public function callback(Request $request, string $provider)
    {
        $driver = Socialite::driver($provider);

        if (method_exists($driver, 'stateless')) {
            $driver = $driver->stateless();
        }

        $socialUser = $driver->user();
        $tenant = $this->resolveTenantFromHost($request->getHost());

        if ($tenant) {
            tenancy()->initialize($tenant);
        }

        $user = User::updateOrCreate(
            ['email' => $socialUser->getEmail()],
            [
                'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: $socialUser->getEmail(),
                'provider_id' => $socialUser->getId(),
                'provider_name' => $provider,
            ]
        );

        if ($tenant) {
            $tenant->users()->syncWithoutDetaching([$user->id]);
            Role::findOrCreate('member', 'web');

            Log::info('Tenant social login attached user', [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            tenancy()->end();
        }

        $token = $user->createToken('react-app-token')->plainTextToken;
        $frontendUrl = $this->frontendUrlForRequest($request);

        return redirect()->away("{$frontendUrl}/auth/callback?token={$token}");
    }

    private function resolveTenantFromHost(string $host): ?Tenant
    {
        $domain = Domain::query()->where('domain', $host)->first();

        if (! $domain) {
            return null;
        }

        return Tenant::query()->find($domain->tenant_id);
    }

    private function frontendUrlForRequest(Request $request): string
    {
        $host = $request->getHost();

        if ($host === 'localhost' || $host === '127.0.0.1') {
            return 'http://localhost:3000';
        }

        return 'http://' . $host . ':3000';
    }
}
