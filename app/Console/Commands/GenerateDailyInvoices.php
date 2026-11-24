<?php

namespace App\Console\Commands;

use App\Models\Certification;
use App\Services\WhatsAppNotifier;
use Illuminate\Console\Command;

class GenerateDailyInvoices extends Command
{
    protected $signature = 'certifications:invoice';
    protected $description = 'Buat tagihan pukul 11:00 untuk semua permohonan berstatus Peserta dan kirim notifikasi WA ke Mitra/Pemohon';

    public function handle(WhatsAppNotifier $wa): int
    {
        $count = 0;

        Certification::with(['user', 'mitra'])->where('status', 'Peserta')->each(function (Certification $cert) use ($wa, &$count) {
            $cert->update([
                'status'             => 'Tagih',
                'nominal_tagihan'    => $cert->nominal_tagihan ?? config('lsp.biaya_default'),
                'tagihan_created_at' => now(),
                'status_updated_at'  => now(),
            ]);

            $target = $cert->mitra ?? $cert->user;
            $wa->send($target?->phone, "Tagihan sertifikasi {$cert->id_izin} telah dibuat. Mohon lunasi paling lambat 1x24 jam.");
            $count++;
        });

        $this->info("{$count} tagihan dibuat.");
        return self::SUCCESS;
    }
}
