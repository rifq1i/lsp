<?php

namespace App\Console\Commands;

use App\Models\Certification;
use Illuminate\Console\Command;

class CloseMitraWindow extends Command
{
    protected $signature = 'certifications:close-mitra-window';
    protected $description = 'Pukul 18:01: data Mitra yang belum di-Get di SIPP diakui sebagai Pemohon langsung';

    public function handle(): int
    {
        $n = Certification::where('status', 'Cek')
            ->where('jenis_permohonan', 'mitra')
            ->whereNull('sipp_get_at')
            ->whereDate('portal_input_at', today())
            ->update(['jenis_permohonan' => 'langsung', 'status_updated_at' => now()]);

        $this->info("{$n} permohonan dialihkan menjadi Pemohon langsung.");
        return self::SUCCESS;
    }
}
