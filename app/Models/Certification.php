<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certification extends Model
{
    use HasFactory;

    protected $table = 'certifications';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'apl_01_data'              => 'array',
            'apl_02_data'              => 'array',
            'kuesioner_data'           => 'array',
            'sipp_data'                => 'array',
            'unit_kompetensi'          => 'array',
            'bukti_persyaratan_dasar'  => 'array',
            'bukti_kompetensi_relevan' => 'array',
            'muk_data'                 => 'array',
            'generated_files'          => 'array',
            'is_approved_by_pemohon'   => 'boolean',
            'is_agreed_asesmen'        => 'boolean',
            'approved_pemohon_at'      => 'datetime',
            'portal_input_at'          => 'datetime',
            'sipp_get_at'              => 'datetime',
            'tagihan_created_at'       => 'datetime',
            'bukti_bayar_at'           => 'datetime',
            'lunas_at'                 => 'datetime',
            'sertifikat_terbit_at'     => 'datetime',
            'jadwal_asesmen'           => 'datetime',
            'status_updated_at'        => 'datetime',
        ];
    }

    // Pemohon / peserta
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Mitra yang menginput lewat portal PU & SIPP
    public function mitra()
    {
        return $this->belongsTo(User::class, 'mitra_id');
    }
}
