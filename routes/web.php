<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BarangayRbiUpdateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentRequestController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/dashboard/municipal', [DashboardController::class, 'municipal'])
        ->middleware('role:'.User::ROLE_MUNICIPAL_LGU)
        ->name('dashboard.municipal');

    Route::get('/dashboard/barangay', [DashboardController::class, 'barangay'])
        ->middleware('role:'.User::ROLE_MUNICIPAL_LGU.','.User::ROLE_BARANGAY)
        ->name('dashboard.barangay');

    Route::post('/barangay/rbi-updates', [BarangayRbiUpdateController::class, 'store'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('barangay.rbi-updates.store');

    Route::put('/barangay/rbi-updates/{rbiUpdate}', [BarangayRbiUpdateController::class, 'update'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('barangay.rbi-updates.update');

    Route::post('/barangay/rbi-updates/{rbiUpdate}/submit', [BarangayRbiUpdateController::class, 'submit'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('barangay.rbi-updates.submit');

    Route::get('/rbi-updates/{rbiUpdate}/download', [BarangayRbiUpdateController::class, 'download'])
        ->middleware('role:'.User::ROLE_MUNICIPAL_LGU.','.User::ROLE_BARANGAY)
        ->name('rbi-updates.download');

    Route::get('/rbi-updates/{rbiUpdate}', [BarangayRbiUpdateController::class, 'show'])
        ->middleware('role:'.User::ROLE_MUNICIPAL_LGU.','.User::ROLE_BARANGAY)
        ->name('rbi-updates.show');

    Route::get('/rbi-updates/{rbiUpdate}/edited-file', [BarangayRbiUpdateController::class, 'exportEdited'])
        ->middleware('role:'.User::ROLE_MUNICIPAL_LGU.','.User::ROLE_BARANGAY)
        ->name('rbi-updates.export-edited');

    Route::get('/dashboard/resident', [DashboardController::class, 'resident'])
        ->middleware('role:'.User::ROLE_MUNICIPAL_LGU.','.User::ROLE_BARANGAY.','.User::ROLE_RESIDENT)
        ->name('dashboard.resident');

    Route::post('/resident/document-requests', [DocumentRequestController::class, 'store'])
        ->middleware('role:'.User::ROLE_RESIDENT)
        ->name('resident.document-requests.store');
});
