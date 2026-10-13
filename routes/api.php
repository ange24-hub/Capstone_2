<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HouseholdSyncController;

Route::middleware(['web', 'auth', 'role:'.\App\Models\User::ROLE_BARANGAY])->group(function () {
    Route::get('/households/offline-sync/session', [HouseholdSyncController::class, 'session'])->name('households.offline-sync.session');
    Route::get('/households/offline-sync/registry', [HouseholdSyncController::class, 'registry'])->name('households.offline-sync.registry');
    Route::post('/households/offline-sync', [HouseholdSyncController::class, 'store'])->name('households.offline-sync');
});

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('resident/v1')->name('api.resident.')->group(function () {
    $mobile = \App\Http\Controllers\Api\ResidentMobileController::class;
    Route::get('barangays', [$mobile, 'barangays']);
    Route::post('login', [$mobile, 'login'])->middleware('throttle:5,1');
    Route::post('register', [$mobile, 'register'])->middleware('throttle:3,1');
    Route::middleware(['auth:sanctum', \App\Http\Middleware\ResidentMobileAccess::class])->group(function () use ($mobile) {
        Route::get('me', [$mobile, 'me']);
        Route::post('logout', [$mobile, 'logout']);
        Route::middleware(\App\Http\Middleware\ResidentMobileAccess::class.':approved')->group(function () use ($mobile) {
            Route::put('profile', [$mobile, 'updateProfile'])->middleware('throttle:5,1');
            Route::get('catalog', [$mobile, 'catalog']);
            Route::get('dashboard', [$mobile, 'dashboard']);
            Route::get('payment-qr', [$mobile, 'paymentQr']);
            Route::get('documents', [$mobile, 'documents']);
            Route::get('documents/{id}', [$mobile, 'document'])->whereNumber('id');
            Route::post('documents', [\App\Http\Controllers\DocumentRequestController::class, 'store'])->middleware('throttle:6,1')->name('documents.store');
            Route::post('documents/{documentRequest}/payment', [\App\Http\Controllers\DocumentPaymentController::class, 'submit'])->middleware('throttle:6,1')->name('payment.submit');
            Route::post('documents/{documentRequest}/in-person-payment', [\App\Http\Controllers\DocumentPaymentController::class, 'inPerson'])->middleware('throttle:6,1')->name('payment.in-person');
            Route::get('concerns', [$mobile, 'concerns']);
            Route::get('concerns/{id}', [$mobile, 'concern'])->whereNumber('id');
            Route::post('concerns', [\App\Http\Controllers\ResidentConcernController::class, 'store'])->middleware('throttle:6,1')->name('concerns.store');
            Route::post('concerns/{concern}/reply', [\App\Http\Controllers\ResidentConcernController::class, 'reply'])->middleware('throttle:10,1')->name('concerns.reply');
        });
    });
});
