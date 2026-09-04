<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\BarangayDocumentRequestController;
use App\Http\Controllers\BarangayPaymentSettingsController;
use App\Http\Controllers\BarangayRbiUpdateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentRequestController;
use App\Http\Controllers\DocumentPaymentController;
use App\Http\Controllers\MigrationDashboardController;
use App\Http\Controllers\RegistryController;
use App\Http\Controllers\ResidentApprovalController;
use App\Http\Controllers\SecretaryApprovalController;
use App\Http\Controllers\SpatialVisualizationController;
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

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : view('welcome'))->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
    Route::get('/services', fn () => view('services'))->name('services');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/assistant/chat', AssistantController::class)
        ->middleware('throttle:30,1')
        ->name('assistant.chat');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/account/pending-approval', [AuthController::class, 'pendingApproval'])
        ->middleware('role:'.User::ROLE_RESIDENT.','.User::ROLE_BARANGAY)
        ->name('approval.pending');

    Route::get('/dashboard/municipal', [DashboardController::class, 'municipal'])
        ->middleware('role:'.User::ROLE_MUNICIPAL_LGU)
        ->name('dashboard.municipal');

    Route::get('/municipal/account-approvals', [DashboardController::class, 'municipalApprovals'])
        ->middleware('role:'.User::ROLE_MUNICIPAL_LGU)
        ->name('municipal.approvals.index');

    Route::get('/municipal/barangays', [DashboardController::class, 'municipalBarangays'])
        ->middleware('role:'.User::ROLE_MUNICIPAL_LGU)
        ->name('municipal.barangays.index');

    Route::put('/municipal/barangays/{barangay}/gcash-profile', [BarangayPaymentSettingsController::class, 'update'])
        ->middleware('role:'.User::ROLE_MUNICIPAL_LGU)
        ->name('municipal.barangays.gcash.update');

    Route::get('/barangays/{barangay}/gcash-qr', [BarangayPaymentSettingsController::class, 'qr'])
        ->middleware('role:'.User::ROLE_MUNICIPAL_LGU.','.User::ROLE_BARANGAY.','.User::ROLE_RESIDENT)
        ->name('barangays.gcash.qr');

    Route::get('/dashboard/barangay', [DashboardController::class, 'barangay'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('dashboard.barangay');

    Route::get('/barangay/resident-approvals', [DashboardController::class, 'barangay'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('barangay.resident-approvals.index');

    Route::get('/barangay/document-requests', [DashboardController::class, 'barangay'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('barangay.document-requests.index');

    Route::get('/barangay/rbi-updates', [DashboardController::class, 'barangay'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('barangay.rbi-updates.index');

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

    Route::get('/rbi-updates/{rbiUpdate}/signatures/{type}/{family?}', [BarangayRbiUpdateController::class, 'signature'])
        ->middleware('role:'.User::ROLE_MUNICIPAL_LGU.','.User::ROLE_BARANGAY)
        ->whereIn('type', ['secretary', 'captain'])
        ->whereNumber('family')
        ->name('rbi-updates.signature');

    Route::get('/rbi-updates/{rbiUpdate}/word-document', [BarangayRbiUpdateController::class, 'exportWord'])
        ->middleware('role:'.User::ROLE_MUNICIPAL_LGU.','.User::ROLE_BARANGAY)
        ->name('rbi-updates.export-word');

    Route::get('/rbi-updates/{rbiUpdate}/pdf', [BarangayRbiUpdateController::class, 'exportPdf'])
        ->middleware('role:'.User::ROLE_MUNICIPAL_LGU.','.User::ROLE_BARANGAY)
        ->name('rbi-updates.export-pdf');

    Route::get('/dashboard/resident', [DashboardController::class, 'resident'])
        ->middleware('role:'.User::ROLE_RESIDENT)
        ->middleware('resident.approved')
        ->name('dashboard.resident');

    Route::get('/resident/request-document', [DashboardController::class, 'resident'])
        ->middleware('role:'.User::ROLE_RESIDENT)
        ->middleware('resident.approved')
        ->name('resident.document-requests.create');

    Route::get('/resident/my-requests', [DashboardController::class, 'resident'])
        ->middleware('role:'.User::ROLE_RESIDENT)
        ->middleware('resident.approved')
        ->name('resident.document-requests.index');

    Route::post('/barangay/residents/{resident}/approve', [ResidentApprovalController::class, 'approve'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('barangay.residents.approve');

    Route::post('/barangay/residents/{resident}/reject', [ResidentApprovalController::class, 'reject'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('barangay.residents.reject');

    Route::post('/municipal/secretaries/{secretary}/approve', [SecretaryApprovalController::class, 'approve'])
        ->middleware('role:'.User::ROLE_MUNICIPAL_LGU)
        ->name('municipal.secretaries.approve');

    Route::post('/municipal/secretaries/{secretary}/reject', [SecretaryApprovalController::class, 'reject'])
        ->middleware('role:'.User::ROLE_MUNICIPAL_LGU)
        ->name('municipal.secretaries.reject');

    Route::put('/barangay/document-requests/{documentRequest}', [BarangayDocumentRequestController::class, 'update'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('barangay.document-requests.update');

    Route::get('/registry', [RegistryController::class, 'index'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('registry.index');

    Route::get('/barangay/resident-registry', [RegistryController::class, 'activeRegistry'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('barangay.registry.active');

    Route::get('/barangay/new-inhabitants', [RegistryController::class, 'newInhabitants'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('barangay.registry.new-inhabitants');

    Route::get('/barangay/deceased-records', [RegistryController::class, 'deceasedRecords'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('barangay.registry.deceased');

    Route::post('/registry', [RegistryController::class, 'store'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('registry.store');

    Route::put('/registry/deceased/{deceasedInhabitant}', [RegistryController::class, 'updateDeceased'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('registry.deceased.update');

    Route::put('/registry/new-inhabitants/{newInhabitant}', [RegistryController::class, 'updateNewInhabitant'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('registry.new-inhabitants.update');

    Route::post('/registry/new-inhabitants', [RegistryController::class, 'storeNewInhabitant'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('registry.new-inhabitants.store');

    Route::post('/registry/new-inhabitant-families', [RegistryController::class, 'storeNewInhabitantFamily'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('registry.new-inhabitant-families.store');

    Route::post('/registry/new-inhabitant-monthly-reports', [RegistryController::class, 'storeNewInhabitantMonthlyReport'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('registry.new-inhabitant-monthly-reports.store');

    Route::delete('/registry/new-inhabitants/{newInhabitant}', [RegistryController::class, 'destroyNewInhabitant'])
        ->middleware('role:'.User::ROLE_BARANGAY)->name('registry.new-inhabitants.destroy');
    Route::get('/registry/new-inhabitants/{newInhabitant}/edit', [RegistryController::class, 'editNewInhabitant'])
        ->middleware('role:'.User::ROLE_BARANGAY)->name('registry.new-inhabitants.edit');
    Route::get('/registry/new-inhabitant-monthly-reports/{month}/pdf', [RegistryController::class, 'downloadNewMonthlyReportPdf'])
        ->middleware('role:'.User::ROLE_BARANGAY)->name('registry.new-inhabitant-monthly-reports.pdf');
    Route::post('/registry/new-inhabitant-monthly-reports/{month}/submit', [RegistryController::class, 'submitNewMonthlyReport'])
        ->middleware('role:'.User::ROLE_BARANGAY)->name('registry.new-inhabitant-monthly-reports.submit');

    Route::post('/registry/new-inhabitant-families/add-to-active', [RegistryController::class, 'addNewFamilyToActive'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('registry.new-inhabitant-families.add-to-active');

    Route::delete('/registry/new-inhabitant-families/remove-from-active', [RegistryController::class, 'removeNewFamilyFromActive'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('registry.new-inhabitant-families.remove-from-active');

    Route::get('/registry/{inhabitant}/edit', [RegistryController::class, 'edit'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('registry.edit');

    Route::put('/registry/{inhabitant}', [RegistryController::class, 'update'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('registry.update');

    Route::delete('/registry/{inhabitant}', [RegistryController::class, 'destroy'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('registry.destroy');

    Route::get('/migration-monitoring', MigrationDashboardController::class)
        ->middleware('role:'.User::ROLE_MUNICIPAL_LGU.','.User::ROLE_BARANGAY)
        ->name('migration.dashboard');

    Route::get('/spatial-visualization', SpatialVisualizationController::class)
        ->middleware('role:'.User::ROLE_MUNICIPAL_LGU.','.User::ROLE_BARANGAY)
        ->name('spatial.index');

    Route::post('/resident/document-requests', [DocumentRequestController::class, 'store'])
        ->middleware('role:'.User::ROLE_RESIDENT)
        ->middleware('resident.approved')
        ->name('resident.document-requests.store');

    Route::post('/resident/document-requests/{documentRequest}/gcash-payment', [DocumentPaymentController::class, 'submit'])
        ->middleware('role:'.User::ROLE_RESIDENT)
        ->middleware('resident.approved')
        ->name('resident.document-payments.submit');

    Route::post('/barangay/document-requests/{documentRequest}/gcash-payment/verify', [DocumentPaymentController::class, 'verify'])
        ->middleware('role:'.User::ROLE_BARANGAY)
        ->name('barangay.document-payments.verify');

    Route::get('/document-requests/{documentRequest}/gcash-payment/proof', [DocumentPaymentController::class, 'proof'])
        ->middleware('role:'.User::ROLE_RESIDENT.','.User::ROLE_BARANGAY)
        ->name('document-payments.proof');
});
