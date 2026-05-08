<?php

use App\Http\Controllers\Admin\AiAssistantController;
use App\Http\Controllers\Subscribe\ManageController;
use App\Http\Controllers\Subscribe\SubscribeController;
use App\Http\Controllers\Subscribe\UnsubscribeController;
use Cachet\Cachet;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::prefix(Cachet::path())
    ->as('subscribe.')
    ->middleware('web')
    ->group(function (): void {
        Route::get('subscribe', [SubscribeController::class, 'create'])->name('create');
        Route::post('subscribe', [SubscribeController::class, 'store'])
            ->middleware('throttle:3,1')
            ->name('store');

        Route::get('subscribe/verify-email/{subscriber}/{code}', [SubscribeController::class, 'verifyEmail'])
            ->where('code', '[A-Za-z0-9]+')
            ->name('verify-email');

        Route::get('subscribe/verify-phone/{subscriber:verify_code}', [SubscribeController::class, 'showVerifyPhone'])
            ->name('verify-phone');
        Route::post('subscribe/verify-phone/{subscriber:verify_code}', [SubscribeController::class, 'verifyPhone'])
            ->name('verify-phone.confirm');

        Route::get('subscribe/manage/{subscriber:verify_code}', [ManageController::class, 'edit'])->name('manage');
        Route::post('subscribe/manage/{subscriber:verify_code}', [ManageController::class, 'update'])->name('manage.update');

        Route::get('subscribe/unsubscribe/{subscriber:verify_code}', [UnsubscribeController::class, 'confirm'])->name('unsubscribe');
        Route::post('subscribe/unsubscribe/{subscriber:verify_code}', [UnsubscribeController::class, 'destroy'])->name('unsubscribe.destroy');
    });

Route::prefix(ltrim(Cachet::dashboardPath(), '/').'/ai')
    ->as('admin.ai.')
    ->middleware(['web', Authenticate::class])
    ->group(function (): void {
        Route::post('incident', [AiAssistantController::class, 'incident'])->name('incident');
        Route::post('incident-update', [AiAssistantController::class, 'incidentUpdate'])->name('incident-update');
        Route::post('maintenance', [AiAssistantController::class, 'maintenance'])->name('maintenance');
    });
