<?php

namespace App\Services;

use App\Models\Certification;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpWord\TemplateProcessor;
use Exception;

class DocumentTemplateService
{
    /**
     * Daftar mapping kode dokumen ke nama file template .docx di storage/app/templates/
     */
    protected array $templates = [
        'FR-APL-01' => 'FR_APL_01.docx',
        'FR-IA-02'  => '10_FR.IA.02 TUGAS PRAKTIK DEMONSTRASI.docx',
        'FR-IA-03'  => '11.FR.IA.03 PERTANYAAN UNTUK MENDUKUNG OBSERVASI.docx',
        'FR-IA-05'  => '13_FR.IA.05 PERTANYAAN TERTULIS PILIHAN GANDA DESAIN GRAFIS MUDA.docx',
        'FR-IA-06'  => '14_FR.IA.06 PERTANYAAN TERTULIS ESAI.docx',
        'FR-IA-07'  => '15. FR.IA.07 PERTANYAAN LISAN.docx',
    ];

    /**
     * Fungsi Utama: Isi Template DOCX lalu ubah ke PDF via ConvertAPI
     */
    public function generatePdfFromTemplate(Certification $cert, string $docType): string
    {
        if (!isset($this->templates[$docType])) {
            throw new Exception("Tipe dokumen {$docType} tidak terdaftar.");
        }

        $templatePath = storage_path('app/templates/' . $this->templates[$docType]);
        if (!file_exists($templatePath)) {
            throw new Exception("File template tidak ditemukan di: {$templatePath}");
        }

        // Buat folder output di storage/app/public/documents/{id_izin}
        $outputDir = storage_path("app/public/documents/{$cert->id_izin}");
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $user = $cert->user;
        $templateProcessor = new TemplateProcessor($templatePath);

        // 1. Isi Variabel Umum & FR-APL-01
        $ttl = ($user->place ?? '-') . ', ' . ($user->date_of_birth ? date('d-m-Y', strtotime($user->date_of_birth)) : '-');

        $templateProcessor->setValues([
            'nama_lengkap'            => $user->fullname ?? $user->name,
            'tempat_tgl_lahir'        => $ttl,
            'jenis_kelamin'           => $cert->jenis_kelamin ?? '-',
            'kebangsaan'              => $cert->kebangsaan ?? 'Indonesia',
            'alamat_rumah'            => $cert->alamat_rumah ?? '-',
            'kode_pos_rumah'          => $cert->kode_pos_rumah ?? '-',
            'telp_rumah'              => $cert->telp_rumah ?? '-',
            'telp_kantor'             => $cert->telp_kantor_pribadi ?? '-',
            'hp'                      => $user->phone ?? '-',
            'email_pribadi'           => $user->email ?? '-',
            'pendidikan_terakhir'     => $cert->pendidikan_terakhir ?? '-',
            'nama_perusahaan'         => $cert->nama_perusahaan ?? '-',
            'jabatan'                 => $cert->jabatan ?? '-',
            'alamat_kantor'           => $cert->alamat_kantor ?? '-',
            'kode_pos_kantor'         => $cert->kode_pos_kantor ?? '-',
            'telp_perusahaan'         => $cert->telp_perusahaan ?? '-',
            'fax_perusahaan'          => $cert->fax_perusahaan ?? '-',
            'email_perusahaan'        => $cert->email_perusahaan ?? '-',
            'judul_skema'             => $cert->judul_skema ?? $cert->skema_sertifikasi ?? '-',
            'nomor_skema'             => $cert->nomor_skema ?? '-',
            'check_sertifikasi'       => ($cert->tujuan_asesmen ?? 'Sertifikasi') === 'Sertifikasi' ? '✔' : '☐',
            'check_sertifikasi_ulang' => ($cert->tujuan_asesmen ?? '') === 'Sertifikasi Ulang' ? '✔' : '☐',
            'rekomendasi_lsp'         => $cert->rekomendasi_lsp ?? 'Belum Diverifikasi',
            'catatan_lsp'             => $cert->catatan_lsp ?? '-',
            'tgl_pemohon'             => $cert->created_at ? $cert->created_at->format('d-m-Y') : date('d-m-Y'),
            'nama_admin_lsp'          => $cert->nama_admin_lsp ?? '-',
            'nik_admin_lsp'           => $cert->nik_admin_lsp ?? '-',
            'tgl_admin_lsp'           => $cert->updated_at ? $cert->updated_at->format('d-m-Y') : '-',
            'nama_asesi'              => $user->fullname ?? $user->name,
            'nama_asesor'             => $cert->nama_asesor ?? '-',
            'no_reg_asesor'           => $cert->no_reg_asesor ?? '-',
            'tanggal_asesmen'         => $cert->jadwal_asesmen ? $cert->jadwal_asesmen->format('d-m-Y') : date('d-m-Y'),
        ]);

        // 2. Simpan hasil .docx yang sudah diisi data
        $docxPath = "{$outputDir}/{$docType}_{$cert->id_izin}.docx";
        $pdfPath  = "{$outputDir}/{$docType}_{$cert->id_izin}.pdf";
        $templateProcessor->saveAs($docxPath);

        // 3. Panggil fungsi ConvertAPI untuk mengubah .docx menjadi .pdf
        return $this->convertDocxToPdfViaApi($docxPath, $pdfPath);
    }

    /**
     * Fungsi Konversi DOCX ke PDF menggunakan ConvertAPI
     */
    protected function convertDocxToPdfViaApi(string $docxPath, string $pdfPath): string
    {
        $secret = env('CONVERTAPI_SECRET');

        if (!$secret) {
            throw new Exception('CONVERTAPI_SECRET belum diatur di file .env');
        }

        $response = Http::timeout(60)
            ->attach('File', file_get_contents($docxPath), basename($docxPath))
            ->post("https://v2.convertapi.com/convert/docx/to/pdf?Secret={$secret}&StoreFile=true");

        if ($response->failed()) {
            throw new Exception('Gagal konversi DOCX ke PDF via API: ' . $response->body());
        }

        // Ambil URL PDF hasil konversi dan simpan ke storage lokal Laravel
        $fileUrl = $response->json('Files.0.Url');
        $pdfContent = Http::get($fileUrl)->body();
        file_put_contents($pdfPath, $pdfContent);

        return $pdfPath;
    }
}