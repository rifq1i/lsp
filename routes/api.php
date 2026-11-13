<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CertificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);   
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'historyLogin']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/admin/register', [AuthController::class, 'registerAdmin']);

    Route::prefix('certifications')->group(function () {
        Route::get('/', [CertificationController::class, 'index']); // List data
        Route::post('/', [CertificationController::class, 'store']); // Hari 1: Buat permohonan (Status: Cek)
        Route::post('/{id}/submit-apl', [CertificationController::class, 'submitApl']); // Hari 1: Isi APL 01 & 02 (Status: Pemohon)
        Route::post('/{id}/approve-apl', [CertificationController::class, 'approveAplByPemohon']); // Hari 2: Approve WA Pemohon (Status: Apl_01)
        Route::post('/{id}/verify-admin', [CertificationController::class, 'verifyByAdmin']); // Hari 3: Verifikasi Admin & Tagihan (Status: Peserta / Tagih)
        Route::post('/{id}/pay', [CertificationController::class, 'confirmPayment']); // Hari 3: Bayar Tagihan (Status: Lunas)
        Route::post('/{id}/pre-assessment', [CertificationController::class, 'setPreAssessment']); // Hari 4: Set Jadwal & Pra Asesmen (Status: Assesor / Pra_Assesment)
        Route::post('/{id}/approve-assessment', [CertificationController::class, 'approveAssessmentByPeserta']); // Hari 5: Isi Kuesioner (Status: Assesment)
        Route::post('/{id}/final-decision', [CertificationController::class, 'submitFinalDecision']); // Hari 6-7: Keputusan & Terbit SKK (Status: Assesor_Yes / Assesor_No)
    });
});