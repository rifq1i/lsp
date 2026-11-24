<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Satu-satunya sumber kebenaran alur status sertifikasi SKK
 * (mengacu pada PDF "Flowchart Pelaksanaan Sertifikasi Kompetensi Kerja Jasa Konstruksi").
 */
class CertificationFlow
{
    public const FINAL_STATUSES = ['Assesor_Yes', 'Assesor_No', 'Batal'];

    /** Transisi status yang diizinkan. */
    public const TRANSITIONS = [
        'Cek'           => ['Pemohon', 'Batal'],
        'Pemohon'       => ['Apl_01', 'Batal'],
        'Apl_01'        => ['Peserta', 'Batal'],
        'Peserta'       => ['Tagih', 'Batal'],
        'Tagih'         => ['Lunas', 'Batal'],
        'Lunas'         => ['Assesor', 'Batal'],
        'Assesor'       => ['Pra_Assesment', 'Assesor_No', 'Batal'],
        'Pra_Assesment' => ['Assesment', 'Assesor_No', 'Batal'],
        'Assesment'     => ['Assesor_Yes', 'Assesor_No'],
    ];

    /** Batas waktu (jam) menunggu tindakan pemohon, sesuai kolom "Waktu" di PDF. */
    public const DEADLINE_HOURS = [
        'Pemohon'       => 24, // menunggu approve APL-01/02 oleh pemohon
        'Tagih'         => 24, // pembayaran paling lambat 1x24 jam
        'Pra_Assesment' => 24, // persetujuan asesmen; lewat = Belum Kompeten
    ];

    /** Status tujuan bila batas waktu terlewat (default: Batal). */
    public const EXPIRED_TARGET = [
        'Pra_Assesment' => 'Assesor_No',
    ];

    /** Tidak ada pergerakan data selama N hari kerja = batal. */
    public const IDLE_WORKING_DAYS = 5;

    /** Jam batas input & Get data oleh Mitra (WIB). */
    public const MITRA_CUTOFF = '18:00';

    /** Tagihan dibuat & batas pelunasan pukul 11:00. */
    public const INVOICE_HOUR = 11;

    /** Asesmen dilaksanakan H+2 setelah lunas. */
    public const ASSESSMENT_DAYS_AFTER_PAYMENT = 2;

    public static function canMove(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function isFinal(string $status): bool
    {
        return in_array($status, self::FINAL_STATUSES, true);
    }

    /** Hitung hari kerja (Senin-Jumat) antara dua waktu. Hari libur nasional belum dihitung. */
    public static function workingDaysBetween(Carbon $from, Carbon $to): int
    {
        $count  = 0;
        $cursor = $from->copy()->startOfDay();
        $end    = $to->copy()->startOfDay();

        while ($cursor->lt($end)) {
            $cursor->addDay();
            if (! $cursor->isWeekend()) {
                $count++;
            }
        }

        return $count;
    }
}
