<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HerbariumController;
use App\Http\Controllers\Api\V1\HerbariumInvitationController;
use App\Http\Controllers\Api\V1\HerbariumMemberController;
use App\Http\Controllers\Api\V1\InstanceController;
use App\Http\Controllers\Api\V1\TokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public instance & health
    Route::get('/health', [InstanceController::class, 'health'])->name('api.v1.health');
    Route::get('/instance', [InstanceController::class, 'show'])->name('api.v1.instance');

    // Public invitation inspection
    Route::get('/invitations/{token}', [HerbariumInvitationController::class, 'showByToken'])
        ->name('api.v1.invitations.show');

    // Public or authenticated herbaria discovery & viewing
    Route::get('/herbaria', [HerbariumController::class, 'index'])->name('api.v1.herbaria.index');
    Route::get('/herbaria/{herbarium:slug}', [HerbariumController::class, 'show'])->name('api.v1.herbaria.show');

    // Authenticated endpoints (Sanctum SPA cookie OR Bearer Personal Access Token)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('api.v1.me');
        Route::patch('/me', [AuthController::class, 'updateProfile'])->name('api.v1.me.update');

        Route::get('/tokens', [TokenController::class, 'index'])->name('api.v1.tokens.index');
        Route::post('/tokens', [TokenController::class, 'store'])->name('api.v1.tokens.store');
        Route::delete('/tokens/{tokenId}', [TokenController::class, 'destroy'])->name('api.v1.tokens.destroy');

        Route::post('/invitations/{token}/accept', [HerbariumInvitationController::class, 'accept'])
            ->name('api.v1.invitations.accept');

        Route::post('/herbaria', [HerbariumController::class, 'store'])->name('api.v1.herbaria.store');
        Route::patch('/herbaria/{herbarium:slug}', [HerbariumController::class, 'update'])->name('api.v1.herbaria.update');
        Route::delete('/herbaria/{herbarium:slug}', [HerbariumController::class, 'destroy'])->name('api.v1.herbaria.destroy');

        Route::get('/herbaria/{herbarium:slug}/members', [HerbariumMemberController::class, 'index']);
        Route::patch('/herbaria/{herbarium:slug}/members/{membership}', [HerbariumMemberController::class, 'update']);
        Route::delete('/herbaria/{herbarium:slug}/members/{membership}', [HerbariumMemberController::class, 'destroy']);

        Route::get('/herbaria/{herbarium:slug}/invitations', [HerbariumInvitationController::class, 'index']);
        Route::post('/herbaria/{herbarium:slug}/invitations', [HerbariumInvitationController::class, 'store']);
        Route::delete('/herbaria/{herbarium:slug}/invitations/{invitation}', [HerbariumInvitationController::class, 'destroy']);
    });
});
