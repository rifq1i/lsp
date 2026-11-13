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
            $table->string('skema_sertifikasi');
            $table->string('jenjang')->nullable();

            $table->json('apl_01_data')->nullable();
            $table->json('apl_02_data')->nullable();
            $table->boolean('is_approved_by_pemohon')->default(false);
            $table->timestamp('approved_pemohon_at')->nullable();

            $table->decimal('nominal_tagihan', 12, 2)->nullable();
            $table->string('bukti_bayar')->nullable();
            $table->timestamp('tagihan_created_at')->nullable();
            $table->timestamp('lunas_at')->nullable();

            $table->string('nama_asesor')->nullable();
            $table->dateTime('jadwal_asesmen')->nullable();
            $table->json('kuesioner_data')->nullable();
            $table->boolean('is_agreed_asesmen')->default(false);

            $table->text('catatan_asesor')->nullable();
            $table->string('nomor_sertifikat_skk')->nullable();
            $table->string('file_sertifikat')->nullable();

            $table->enum('status', [
                'Cek',
                'Pemohon',
                'Apl_01',
                'Peserta',
                'Tagih',
                'Lunas',
                'Assesor',
                'Pra_Assesment',
                'Assesment',
                'Assesor_Yes',
                'Assesor_No',
                'Batal'
            ])->default('Cek');

            $table->timestamp('status_updated_at')->useCurrent();
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
