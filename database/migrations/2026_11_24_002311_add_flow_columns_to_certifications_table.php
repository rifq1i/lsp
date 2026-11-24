<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certifications', function (Blueprint $table) {
            $add = function (string $col, callable $def) use ($table) {
                if (! Schema::hasColumn('certifications', $col)) {
                    $def($table);
                }
            };

            // Kolom yang dipakai controller/model tetapi belum ada di migrasi awal
            $add('apl_01_data', fn ($t) => $t->json('apl_01_data')->nullable());
            $add('kuesioner_data', fn ($t) => $t->json('kuesioner_data')->nullable());
            $add('is_approved_by_pemohon', fn ($t) => $t->boolean('is_approved_by_pemohon')->default(false));
            $add('approved_pemohon_at', fn ($t) => $t->timestamp('approved_pemohon_at')->nullable());
            $add('is_agreed_asesmen', fn ($t) => $t->boolean('is_agreed_asesmen')->default(false));
            $add('nominal_tagihan', fn ($t) => $t->decimal('nominal_tagihan', 14, 2)->nullable());
            $add('tagihan_created_at', fn ($t) => $t->timestamp('tagihan_created_at')->nullable());
            $add('bukti_bayar', fn ($t) => $t->string('bukti_bayar')->nullable());
            $add('bukti_bayar_at', fn ($t) => $t->timestamp('bukti_bayar_at')->nullable());
            $add('lunas_at', fn ($t) => $t->timestamp('lunas_at')->nullable());
            $add('catatan_asesor', fn ($t) => $t->text('catatan_asesor')->nullable());
            $add('nomor_sertifikat_skk', fn ($t) => $t->string('nomor_sertifikat_skk')->nullable());
            $add('sertifikat_terbit_at', fn ($t) => $t->timestamp('sertifikat_terbit_at')->nullable());
            $add('status_updated_at', fn ($t) => $t->timestamp('status_updated_at')->nullable());

            // Kolom baru sesuai PDF (Mitra, portal PU, SIPP)
            $add('mitra_id', fn ($t) => $t->foreignId('mitra_id')->nullable()->constrained('users')->nullOnDelete());
            $add('nomor_registrasi', fn ($t) => $t->string('nomor_registrasi')->nullable());
            $add('portal_input_at', fn ($t) => $t->timestamp('portal_input_at')->nullable());
            $add('sipp_data', fn ($t) => $t->json('sipp_data')->nullable());
            $add('sipp_get_at', fn ($t) => $t->timestamp('sipp_get_at')->nullable());
            $add('pra_asesmen_catatan', fn ($t) => $t->text('pra_asesmen_catatan')->nullable());
            $add('batal_alasan', fn ($t) => $t->string('batal_alasan')->nullable());
        });
    }

    public function down(): void
    {
        // Sengaja kosong: kolom lama mungkin sudah dipakai data produksi.
    }
};
