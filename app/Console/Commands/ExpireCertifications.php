<?php

namespace App\Console\Commands;

use App\Models\Certification;
use App\Support\CertificationFlow;
use Illuminate\Console\Command;

class ExpireCertifications extends Command
{
    protected $signature = 'certifications:expire';
    protected $description = 'Batalkan permohonan yang lewat 1x24 jam atau 5 hari kerja tanpa pergerakan (sesuai flowchart PDF)';

    public function handle(): int
    {
        $now   = now();
        $total = 0;

        Certification::whereNotIn('status', CertificationFlow::FINAL_STATUSES)
            ->each(function (Certification $cert) use ($now, &$total) {
                $since = $cert->status_updated_at ?? $cert->updated_at ?? $cert->created_at;

                // 1) Batas 1x24 jam
                $limit = CertificationFlow::DEADLINE_HOURS[$cert->status] ?? null;
                if ($limit !== null && $since->diffInHours($now, true) >= $limit) {
                    $target = CertificationFlow::EXPIRED_TARGET[$cert->status] ?? 'Batal';
                    $cert->update([
                        'status'            => $target,
                        'batal_alasan'      => "Melewati batas {$limit} jam pada status {$cert->status}",
                        'status_updated_at' => $now,
                    ]);
                    $total++;
                    return;
                }

                // 2) 5 hari kerja tanpa pergerakan data
                if (CertificationFlow::workingDaysBetween($since, $now) >= CertificationFlow::IDLE_WORKING_DAYS) {
                    $cert->update([
                        'status'            => 'Batal',
                        'batal_alasan'      => '5 hari kerja tidak ada pergerakan data',
                        'status_updated_at' => $now,
                    ]);
                    $total++;
                }
            });

        $this->info("{$total} permohonan diperbarui.");
        return self::SUCCESS;
    }
}
