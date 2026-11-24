<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CertificationController;
use App\Http\Controllers\SignatureController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/register', [AuthController::class, 'registerUser'])->middleware('throttle:10,1');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'historyLogin']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/user/signature', [SignatureController::class, 'upload']);

    // Daftar & unduh dokumen (hak akses dicek di controller per permohonan)
    Route::prefix('certifications')->group(function () {
        Route::get('/', [CertificationController::class, 'index']);
        Route::get('/{id}/pdf/{jenis_dokumen}', [CertificationController::class, 'downloadPdf']);
    });

    // Pembuatan akun Mitra & Asesor: Admin LSP atau Super Admin
    Route::middleware('role:admin,super_admin')->prefix('super-admin')->group(function () {
        Route::post('/register-mitra', [AuthController::class, 'registerMitra']);
        Route::post('/register-asesor', [AuthController::class, 'registerAsesor']);
    });

    // Manajemen akun sensitif: HANYA Super Admin (sebelumnya Admin biasa bisa membuat Super Admin)
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

    Route::prefix('certifications')->group(function () {
        // Pemohon langsung & Mitra: buat permohonan (status Cek), isi APL, kirim bukti bayar
        Route::middleware('role:user,mitra')->group(function () {
            Route::post('/', [CertificationController::class, 'store']);
            Route::post('/{id}/submit-apl', [CertificationController::class, 'submitApl']);
            Route::post('/{id}/pay', [CertificationController::class, 'submitPaymentProof']);
        });

        // Hanya Mitra: Get data di SIPP
        Route::post('/{id}/get-sipp', [CertificationController::class, 'getFromSipp'])->middleware('role:mitra');

        // Hanya Pemohon/Peserta
        Route::middleware('role:user')->group(function () {
            Route::post('/apl-01', [CertificationController::class, 'submitApl01']);
            Route::post('/{id}/approve-apl', [CertificationController::class, 'approveAplByPemohon']);
            Route::post('/{id}/approve-assessment', [CertificationController::class, 'approveAssessmentByPeserta']);
        });

        // Admin LSP
        Route::middleware('role:admin,super_admin')->group(function () {
            Route::post('/{id}/review-apl01', [CertificationController::class, 'reviewApl01']);
            Route::post('/{id}/nominal', [CertificationController::class, 'setInvoiceAmount']);
            Route::post('/{id}/confirm-payment', [CertificationController::class, 'confirmPayment']);
            Route::post('/{id}/schedule', [CertificationController::class, 'scheduleAssessment']);
        });

        // Asesor LSP (admin tetap boleh sebagai cadangan)
        Route::middleware('role:asesor,admin,super_admin')->group(function () {
            Route::post('/{id}/pre-assessment', [CertificationController::class, 'setPreAssessment']);
            Route::post('/{id}/final-decision', [CertificationController::class, 'submitFinalDecision']);
        });
    });
});
