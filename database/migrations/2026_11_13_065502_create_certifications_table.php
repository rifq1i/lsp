<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('id_izin')->unique();
            $table->enum('jenis_permohonan', ['mitra', 'langsung'])->default('mitra');

            $table->string('jenis_kelamin')->nullable();
            $table->string('kebangsaan')->default('Indonesia');
            $table->text('alamat_rumah')->nullable();
            $table->string('kode_pos_rumah', 10)->nullable();
            $table->string('telp_rumah', 20)->nullable();
            $table->string('telp_kantor_pribadi', 20)->nullable();
            $table->string('pendidikan_terakhir')->nullable();

            $table->string('nama_perusahaan')->nullable();
            $table->string('jabatan')->nullable();
            $table->text('alamat_kantor')->nullable();
            $table->string('kode_pos_kantor', 10)->nullable();
            $table->string('telp_perusahaan', 20)->nullable();
            $table->string('fax_perusahaan', 20)->nullable();
            $table->string('email_perusahaan')->nullable();

            $table->string('judul_skema');
            $table->string('nomor_skema')->nullable();
            $table->enum('tujuan_asesmen', ['Sertifikasi', 'Sertifikasi Ulang'])->default('Sertifikasi');
            $table->json('unit_kompetensi')->nullable();

            $table->json('bukti_persyaratan_dasar')->nullable();
            $table->json('bukti_kompetensi_relevan')->nullable();
            $table->enum('rekomendasi_lsp', ['Diterima', 'Tidak diterima'])->nullable();
            $table->text('catatan_lsp')->nullable();
            $table->string('ttd_pemohon_path')->nullable();
            $table->string('nama_admin_lsp')->nullable();
            $table->string('nik_admin_lsp')->nullable();
            $table->string('ttd_admin_path')->nullable();

            $table->json('apl_02_data')->nullable();
            $table->string('nama_asesor')->nullable();
            $table->string('no_reg_asesor')->nullable();
            $table->dateTime('jadwal_asesmen')->nullable();
            $table->json('muk_data')->nullable();

            $table->json('generated_files')->nullable();
            $table->enum('status', [
                'Cek', 'Pemohon', 'Apl_01', 'Peserta', 'Tagih', 'Lunas',
                'Assesor', 'Pra_Assesment', 'Assesment', 'Assesor_Yes', 'Assesor_No', 'Batal'
            ])->default('Cek');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certifications');
    }
};
