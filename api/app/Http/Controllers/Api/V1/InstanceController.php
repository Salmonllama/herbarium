<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\HerbariumResource;
use App\Models\Herbarium;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class InstanceController extends Controller
{
    public function health(): JsonResponse
    {
        $dbOk = false;
        $redisOk = false;

        try {
            DB::select('SELECT 1');
            $dbOk = true;
        } catch (Throwable) {
            $dbOk = false;
        }

        if (config('cache.default') === 'redis' || config('queue.default') === 'redis') {
            try {
                Redis::ping();
                $redisOk = true;
            } catch (Throwable) {
                $redisOk = false;
            }
        } else {
            $redisOk = true;
        }

        $healthy = $dbOk && $redisOk;

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => [
                'database' => $dbOk,
                'redis' => $redisOk,
            ],
            'version' => '1.0.0',
        ], $healthy ? 200 : 503);
    }

    public function show(Request $request): JsonResponse
    {
        $host = strtolower($request->header('X-Forwarded-Host') ?: $request->getHost());
        $hostWithoutPort = explode(':', $host)[0];

        $customDomainHerbarium = Herbarium::query()
            ->whereRaw('LOWER(custom_domain) = ?', [$hostWithoutPort])
            ->first();

        $defaultSlug = config('herbarium.default_herbarium');
        $defaultHerbarium = $customDomainHerbarium
            ?? ($defaultSlug ? Herbarium::query()->where('slug', $defaultSlug)->first() : null);

        $providers = [];
        if (config('herbarium.auth_providers.orcid.enabled')) {
            $providers[] = [
                'id' => 'orcid',
                'label' => config('herbarium.auth_providers.orcid.label', 'ORCID'),
                'redirect_url' => '/auth/orcid/redirect',
            ];
        }
        if (config('herbarium.auth_providers.oidc.enabled')) {
            $providers[] = [
                'id' => 'oidc',
                'label' => config('herbarium.auth_providers.oidc.label', 'Institution SSO'),
                'redirect_url' => '/auth/oidc/redirect',
            ];
        }

        return response()->json([
            'app_name' => config('app.name', 'Herbarium'),
            'registration_mode' => config('herbarium.registration_mode', 'open'),
            'default_herbarium_slug' => $defaultHerbarium?->slug,
            'custom_domain_herbarium' => $customDomainHerbarium ? new HerbariumResource($customDomainHerbarium) : null,
            'auth_providers' => $providers,
        ]);
    }
}
