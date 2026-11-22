<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OAuthIdentity;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SocialAuthController extends Controller
{
    public function redirect(Request $request, string $provider): RedirectResponse|JsonResponse
    {
        if (! in_array($provider, ['orcid', 'oidc'], true)) {
            return response()->json(['message' => 'Unsupported auth provider.'], 404);
        }

        $cfg = config("herbarium.auth_providers.{$provider}");
        if (empty($cfg['enabled'])) {
            return response()->json(['message' => strtoupper($provider).' authentication is not configured.'], 404);
        }

        $state = Str::random(40);
        $request->session()->put("oauth_state_{$provider}", $state);

        if ($provider === 'orcid') {
            $host = ! empty($cfg['sandbox']) ? 'https://sandbox.orcid.org' : 'https://orcid.org';
            $query = http_build_query([
                'client_id' => $cfg['client_id'],
                'response_type' => 'code',
                'scope' => '/authenticate',
                'redirect_uri' => $cfg['redirect'] ?: rtrim((string) config('app.url'), '/').'/auth/orcid/callback',
                'state' => $state,
            ]);

            return redirect()->away("{$host}/oauth/authorize?{$query}");
        }

        // Generic OIDC discovery
        $discovery = $this->discoverOidc((string) $cfg['issuer']);
        if (! $discovery || empty($discovery['authorization_endpoint'])) {
            return response()->json(['message' => 'Unable to discover OIDC authorization endpoint.'], 502);
        }

        $query = http_build_query([
            'client_id' => $cfg['client_id'],
            'response_type' => 'code',
            'scope' => $cfg['scopes'] ?? 'openid profile email',
            'redirect_uri' => $cfg['redirect'] ?: rtrim((string) config('app.url'), '/').'/auth/oidc/callback',
            'state' => $state,
        ]);

        return redirect()->away($discovery['authorization_endpoint'].'?'.$query);
    }

    public function callback(Request $request, string $provider): RedirectResponse|JsonResponse
    {
        if (! in_array($provider, ['orcid', 'oidc'], true)) {
            return response()->json(['message' => 'Unsupported auth provider.'], 404);
        }

        $cfg = config("herbarium.auth_providers.{$provider}");
        if (empty($cfg['enabled'])) {
            return response()->json(['message' => strtoupper($provider).' authentication is not configured.'], 404);
        }

        $expectedState = $request->session()->pull("oauth_state_{$provider}");
        if (! $expectedState || $request->string('state')->toString() !== $expectedState) {
            return response()->json(['message' => 'Invalid OAuth state parameter.'], 400);
        }

        $code = $request->string('code')->toString();
        if ($code === '') {
            return response()->json(['message' => 'Authorization code missing.'], 400);
        }

        $profile = $provider === 'orcid'
            ? $this->exchangeOrcidCode($cfg, $code)
            : $this->exchangeOidcCode($cfg, $code);

        if (! $profile || empty($profile['provider_user_id'])) {
            return response()->json(['message' => 'Failed to authenticate with identity provider.'], 502);
        }

        $user = $this->resolveOrCreateUser($provider, $profile);
        if (! $user) {
            return response()->json(['message' => 'Registration is invite-only on this instance.'], 403);
        }

        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();

        return redirect()->to('/app');
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return ?array{provider_user_id: string, email: ?string, name: string, orcid_id?: string}
     */
    private function exchangeOrcidCode(array $cfg, string $code): ?array
    {
        $host = ! empty($cfg['sandbox']) ? 'https://sandbox.orcid.org' : 'https://orcid.org';
        $res = Http::asForm()
            ->acceptJson()
            ->post("{$host}/oauth/token", [
                'client_id' => $cfg['client_id'],
                'client_secret' => $cfg['client_secret'],
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $cfg['redirect'] ?: rtrim((string) config('app.url'), '/').'/auth/orcid/callback',
            ]);

        if (! $res->successful()) {
            return null;
        }

        $orcid = (string) $res->json('orcid', '');
        if ($orcid === '') {
            return null;
        }

        return [
            'provider_user_id' => $orcid,
            'orcid_id' => $orcid,
            'email' => $res->json('email'),
            'name' => trim((string) $res->json('name')) ?: "ORCID Researcher ({$orcid})",
        ];
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return ?array{provider_user_id: string, email: ?string, name: string}
     */
    private function exchangeOidcCode(array $cfg, string $code): ?array
    {
        $discovery = $this->discoverOidc((string) $cfg['issuer']);
        if (! $discovery || empty($discovery['token_endpoint'])) {
            return null;
        }

        $tokenRes = Http::asForm()
            ->acceptJson()
            ->post($discovery['token_endpoint'], [
                'client_id' => $cfg['client_id'],
                'client_secret' => $cfg['client_secret'],
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $cfg['redirect'] ?: rtrim((string) config('app.url'), '/').'/auth/oidc/callback',
            ]);

        if (! $tokenRes->successful()) {
            return null;
        }

        $accessToken = (string) $tokenRes->json('access_token', '');
        $claims = [];

        if (! empty($discovery['userinfo_endpoint']) && $accessToken !== '') {
            $userInfoRes = Http::withToken($accessToken)->acceptJson()->get($discovery['userinfo_endpoint']);
            if ($userInfoRes->successful() && is_array($userInfoRes->json())) {
                $claims = $userInfoRes->json();
            }
        }

        $sub = (string) ($claims['sub'] ?? '');
        if ($sub === '') {
            return null;
        }

        return [
            'provider_user_id' => $sub,
            'email' => isset($claims['email']) ? strtolower((string) $claims['email']) : null,
            'name' => trim((string) ($claims['name'] ?? $claims['preferred_username'] ?? 'Institutional User')),
        ];
    }

    /**
     * @return ?array<string, mixed>
     */
    private function discoverOidc(string $issuer): ?array
    {
        $url = rtrim($issuer, '/').'/.well-known/openid-configuration';

        return Cache::remember('herbarium:oidc:'.md5($url), 3600, function () use ($url) {
            $res = Http::timeout(8)->acceptJson()->get($url);

            return $res->successful() && is_array($res->json()) ? $res->json() : null;
        });
    }

    /**
     * @param  array{provider_user_id: string, email: ?string, name: string, orcid_id?: string}  $profile
     */
    private function resolveOrCreateUser(string $provider, array $profile): ?User
    {
        $existingIdentity = OAuthIdentity::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $profile['provider_user_id'])
            ->first();

        if ($existingIdentity) {
            return $existingIdentity->user;
        }

        return DB::transaction(function () use ($provider, $profile) {
            $user = null;
            if (! empty($profile['email'])) {
                $user = User::query()->where('email', strtolower($profile['email']))->first();
            }

            if (! $user) {
                if (config('herbarium.registration_mode', 'open') === 'invite_only' && User::query()->exists()) {
                    return null;
                }

                $email = $profile['email'] ?: strtolower("{$provider}-".Str::slug($profile['provider_user_id']).'@users.herbarium.local');
                $user = User::create([
                    'name' => $profile['name'],
                    'email' => $email,
                    'orcid_id' => $profile['orcid_id'] ?? null,
                    'email_verified_at' => now(),
                    'is_instance_admin' => ! User::query()->exists(),
                ]);
            } elseif (! empty($profile['orcid_id']) && ! $user->orcid_id) {
                $user->update(['orcid_id' => $profile['orcid_id']]);
            }

            OAuthIdentity::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_user_id' => $profile['provider_user_id'],
                'email' => $profile['email'],
                'name' => $profile['name'],
            ]);

            return $user;
        });
    }
}
