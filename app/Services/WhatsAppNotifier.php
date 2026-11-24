<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Placeholder notifikasi WA sesuai PDF (notif pernyataan APL-01/02, persetujuan asesmen, tagihan).
 * Saat ini hanya menulis ke log. Ganti isi send() dengan provider WA (Fonnte/Wablas/WA Cloud API).
 */
class WhatsAppNotifier
{
    public function send(?string $phone, string $message): void
    {
        if (empty($phone)) {
            Log::warning('[WA] Nomor tujuan kosong, notifikasi dilewati: ' . $message);
            return;
        }

        Log::info("[WA -> {$phone}] {$message}");
    }
}
