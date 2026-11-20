<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CertificationController;
use App\Http\Controllers\SignatureController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'registerUser']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'historyLogin']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/user/signature', [SignatureController::class, 'upload']);

    Route::prefix('certifications')->group(function () {
        Route::get('/', [CertificationController::class, 'index']);
        Route::get('/{id}/pdf/{jenis_dokumen}', [CertificationController::class, 'downloadPdf']);
    });

    Route::middleware('role:super_admin')->prefix('super-admin')->group(function () {
        Route::post('/register-admin', [AuthController::class, 'registerAdmin']);
        Route::post('/register-super-admin', [AuthController::class, 'registerSuperAdmin']);

        Route::prefix('users')->group(function () {
            Route::get('/', [UserManagementController::class, 'index']);
            Route::get('/{id}', [UserManagementController::class, 'show']);
            Route::put('/{id}', [UserManagementController::class, 'update']);
            Route::delete('/{id}', [UserManagementController::class, 'destroy']);
        });
    });

    Route::middleware('role:admin,super_admin')->prefix('certifications')->group(function () {
        Route::post('/{id}/verify-admin', [CertificationController::class, 'verifyByAdmin']);
        Route::post('/{id}/pre-assessment', [CertificationController::class, 'setPreAssessment']);
        Route::post('/{id}/final-decision', [CertificationController::class, 'submitFinalDecision']);
    });

    Route::middleware('role:user')->prefix('certifications')->group(function () {
        Route::post('/', [CertificationController::class, 'store']);
        Route::post('/apl-01', [CertificationController::class, 'submitApl01']);
        Route::post('/{id}/submit-apl', [CertificationController::class, 'submitApl']);
        Route::post('/{id}/approve-apl', [CertificationController::class, 'approveAplByPemohon']);
        Route::post('/{id}/pay', [CertificationController::class, 'confirmPayment']);
        Route::post('/{id}/approve-assessment', [CertificationController::class, 'approveAssessmentByPeserta']);
    });
});