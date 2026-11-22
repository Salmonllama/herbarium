<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeterminationController;
use App\Http\Controllers\Api\V1\HerbariumController;
use App\Http\Controllers\Api\V1\HerbariumInvitationController;
use App\Http\Controllers\Api\V1\HerbariumMemberController;
use App\Http\Controllers\Api\V1\InstanceController;
use App\Http\Controllers\Api\V1\SpecimenController;
use App\Http\Controllers\Api\V1\TaxonController;
use App\Http\Controllers\Api\V1\TaxonLookupController;
use App\Http\Controllers\Api\V1\TokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public instance & health
    Route::get('/health', [InstanceController::class, 'health'])->name('api.v1.health');
    Route::get('/instance', [InstanceController::class, 'show'])->name('api.v1.instance');

    // Public invitation inspection
    Route::get('/invitations/{token}', [HerbariumInvitationController::class, 'showByToken'])
        ->name('api.v1.invitations.show');

    // Public or authenticated herbaria discovery & browsing
    Route::get('/herbaria', [HerbariumController::class, 'index'])->name('api.v1.herbaria.index');
    Route::get('/herbaria/{herbarium:slug}', [HerbariumController::class, 'show'])->name('api.v1.herbaria.show');

    // Taxa browsing & tree (public if herbarium is public/unlisted)
    Route::get('/herbaria/{herbarium:slug}/taxa', [TaxonController::class, 'index']);
    Route::get('/herbaria/{herbarium:slug}/taxa/{taxon}', [TaxonController::class, 'show']);
    Route::get('/herbaria/{herbarium:slug}/taxonomy/tree', [TaxonController::class, 'tree']);

    // Specimens browsing, facets & determinations (public if published & herbarium accessible)
    Route::get('/herbaria/{herbarium:slug}/specimens', [SpecimenController::class, 'index']);
    Route::get('/herbaria/{herbarium:slug}/specimens/facets', [SpecimenController::class, 'facets']);
    Route::get('/herbaria/{herbarium:slug}/specimens/{catalogNumber}', [SpecimenController::class, 'show']);
    Route::get('/herbaria/{herbarium:slug}/specimens/{catalogNumber}/determinations', [DeterminationController::class, 'index']);

    // Authenticated endpoints (Sanctum SPA cookie OR Bearer Personal Access Token)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('api.v1.me');
        Route::patch('/me', [AuthController::class, 'updateProfile'])->name('api.v1.me.update');

        Route::get('/tokens', [TokenController::class, 'index'])->name('api.v1.tokens.index');
        Route::post('/tokens', [TokenController::class, 'store'])->name('api.v1.tokens.store');
        Route::delete('/tokens/{tokenId}', [TokenController::class, 'destroy'])->name('api.v1.tokens.destroy');

        Route::post('/invitations/{token}/accept', [HerbariumInvitationController::class, 'accept'])
            ->name('api.v1.invitations.accept');

        // External taxonomic backbone autocomplete (GBIF / Kew POWO)
        Route::get('/taxonomy/lookup', TaxonLookupController::class)->name('api.v1.taxonomy.lookup');

        // Herbaria management
        Route::post('/herbaria', [HerbariumController::class, 'store'])->name('api.v1.herbaria.store');
        Route::patch('/herbaria/{herbarium:slug}', [HerbariumController::class, 'update'])->name('api.v1.herbaria.update');
        Route::delete('/herbaria/{herbarium:slug}', [HerbariumController::class, 'destroy'])->name('api.v1.herbaria.destroy');

        Route::get('/herbaria/{herbarium:slug}/members', [HerbariumMemberController::class, 'index']);
        Route::patch('/herbaria/{herbarium:slug}/members/{membership}', [HerbariumMemberController::class, 'update']);
        Route::delete('/herbaria/{herbarium:slug}/members/{membership}', [HerbariumMemberController::class, 'destroy']);

        Route::get('/herbaria/{herbarium:slug}/invitations', [HerbariumInvitationController::class, 'index']);
        Route::post('/herbaria/{herbarium:slug}/invitations', [HerbariumInvitationController::class, 'store']);
        Route::delete('/herbaria/{herbarium:slug}/invitations/{invitation}', [HerbariumInvitationController::class, 'destroy']);

        // Taxa CRUD
        Route::post('/herbaria/{herbarium:slug}/taxa', [TaxonController::class, 'store']);
        Route::patch('/herbaria/{herbarium:slug}/taxa/{taxon}', [TaxonController::class, 'update']);
        Route::delete('/herbaria/{herbarium:slug}/taxa/{taxon}', [TaxonController::class, 'destroy']);

        // Specimens CRUD & workflow
        Route::post('/herbaria/{herbarium:slug}/specimens', [SpecimenController::class, 'store']);
        Route::patch('/herbaria/{herbarium:slug}/specimens/{catalogNumber}', [SpecimenController::class, 'update']);
        Route::post('/herbaria/{herbarium:slug}/specimens/{catalogNumber}/publish', [SpecimenController::class, 'publish']);
        Route::post('/herbaria/{herbarium:slug}/specimens/{catalogNumber}/unpublish', [SpecimenController::class, 'unpublish']);
        Route::delete('/herbaria/{herbarium:slug}/specimens/{catalogNumber}', [SpecimenController::class, 'destroy']);

        // Determinations
        Route::post('/herbaria/{herbarium:slug}/specimens/{catalogNumber}/determinations', [DeterminationController::class, 'store']);
    });
});
