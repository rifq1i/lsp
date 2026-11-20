<?php

namespace App\Http\Controllers;

use App\Models\Certification;
use App\Services\DocumentTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;
use setasign\Fpdi\Fpdi;

class CertificationController extends Controller
{

    protected $documentService;
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'admin' || $user->role === 'super_admin') {
            $data = Certification::with('user:id,name,fullname,email,phone')->latest()->get();
        } else {
            $data = Certification::where('user_id', $user->id)->latest()->get();
        }

        return response()->json([
            'success' => true,
            'data'    => $data
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'judul_skema'       => 'nullable|string|max:255',
            'skema_sertifikasi' => 'nullable|string|max:255',
            'jenis_kelamin'     => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Ambil hanya kolom yang benar-benar ada di tabel certifications
        $data = $request->only([
            'jenis_permohonan',
            'jenjang',
            'jenis_kelamin',
            'kebangsaan',
            'alamat_rumah',
            'kode_pos_rumah',
            'telp_rumah',
            'telp_kantor_pribadi',
            'pendidikan_terakhir',
            'nama_perusahaan',
            'jabatan',
            'alamat_kantor',
            'kode_pos_kantor',
            'telp_perusahaan',
            'fax_perusahaan',
            'email_perusahaan',
            'nomor_skema',
            'tujuan_asesmen',
            'unit_kompetensi',
            'bukti_persyaratan_dasar',
            'bukti_kompetensi_relevan',
            'rekomendasi_lsp',
            'catatan_lsp',
            'nama_admin_lsp',
            'nik_admin_lsp',
        ]);

        $data['user_id']     = $request->user()->id;
        $data['id_izin']     = 'APL-' . strtoupper(Str::random(8));
        $data['judul_skema'] = $request->judul_skema ?? $request->skema_sertifikasi ?? 'Skema Belum Diisi';
        $data['status']      = 'Pemohon';

        $certification = Certification::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Permohonan sertifikasi berhasil dibuat.',
            'data'    => $certification
        ], 201);
    }

    public function submitApl(Request $request, $id)
    {
        $certification = Certification::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'apl_01_data' => 'required|array',
            'apl_02_data' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data APL 01 dan APL 02 wajib diisi berupa objek/array JSON.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $certification->update([
            'apl_01_data'       => $request->apl_01_data,
            'apl_02_data'       => $request->apl_02_data,
            'status'            => 'Pemohon',
            'status_updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'APL 01 & APL 02 tersimpan. Status berubah menjadi Pemohon (Notif WA terkirim ke Pemohon).',
            'data'    => $certification
        ], 200);
    }

    public function approveAplByPemohon(Request $request, $id)
    {
        $certification = Certification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($certification->status !== 'Pemohon') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya permohonan berstatus Pemohon yang dapat di-approve.'
            ], 400);
        }

        $certification->update([
            'is_approved_by_pemohon' => true,
            'approved_pemohon_at'    => now(),
            'status'                 => 'Apl_01',
            'status_updated_at'      => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data APL 01 & APL 02 disetujui oleh Pemohon. Status berubah menjadi Apl_01.',
            'data'    => $certification
        ], 200);
    }

    public function verifyByAdmin(Request $request, $id)
    {
        if ($request->user()->role === 'user') {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status_verifikasi' => 'required|in:Peserta,Tagih',
            'nominal_tagihan'   => 'required_if:status_verifikasi,Tagih|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $certification = Certification::findOrFail($id);

        $certification->update([
            'status'             => $request->status_verifikasi,
            'nominal_tagihan'    => $request->nominal_tagihan ?? $certification->nominal_tagihan,
            'tagihan_created_at' => $request->status_verifikasi === 'Tagih' ? now() : $certification->tagihan_created_at,
            'status_updated_at'  => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status berhasil diperbarui oleh Admin menjadi ' . $request->status_verifikasi,
            'data'    => $certification
        ], 200);
    }

    public function confirmPayment(Request $request, $id)
    {
        $certification = Certification::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'bukti_bayar' => 'required|string', // Bisa diisi URL gambar atau kode referensi pembayaran
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $certification->update([
            'bukti_bayar'       => $request->bukti_bayar,
            'lunas_at'          => now(),
            'status'            => 'Lunas',
            'status_updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran terkonfirmasi. Status berubah menjadi Lunas.',
            'data'    => $certification
        ], 200);
    }

    public function setPreAssessment(Request $request, $id)
    {
        if ($request->user()->role === 'user') {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'nama_asesor'    => 'required|string|max:255',
            'jadwal_asesmen' => 'required|date',
            'status'         => 'required|in:Assesor,Pra_Assesment',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $certification = Certification::findOrFail($id);

        $certification->update([
            'nama_asesor'       => $request->nama_asesor,
            'jadwal_asesmen'    => $request->jadwal_asesmen,
            'status'            => $request->status,
            'status_updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Jadwal & Pra-Asesmen berhasil diperbarui ke status ' . $request->status,
            'data'    => $certification
        ], 200);
    }

    public function approveAssessmentByPeserta(Request $request, $id)
    {
        $certification = Certification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'kuesioner_data' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $certification->update([
            'kuesioner_data'    => $request->kuesioner_data,
            'is_agreed_asesmen' => true,
            'status'            => 'Assesment',
            'status_updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Persetujuan asesmen dan kuesioner diterima. Status berubah menjadi Assesment.',
            'data'    => $certification
        ], 200);
    }

    public function submitFinalDecision(Request $request, $id)
    {
        if ($request->user()->role === 'user') {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'hasil'                => 'required|in:Assesor_Yes,Assesor_No',
            'catatan_asesor'       => 'nullable|string',
            'nomor_sertifikat_skk' => 'required_if:hasil,Assesor_Yes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $certification = Certification::findOrFail($id);

        $certification->update([
            'status'               => $request->hasil,
            'catatan_asesor'       => $request->catatan_asesor,
            'nomor_sertifikat_skk' => $request->nomor_sertifikat_skk,
            'status_updated_at'    => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $request->hasil === 'Assesor_Yes'
                ? 'Peserta dinyatakan KOMPETEN (Assesor_Yes) dan Sertifikat SKK diterbitkan.'
                : 'Peserta dinyatakan BELUM KOMPETEN (Assesor_No).',
            'data'    => $certification
        ], 200);
    }

    public function submitApl01(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jenis_kelamin'       => 'required|in:Laki-laki,Wanita',
            'kebangsaan'          => 'nullable|string',
            'alamat_rumah'        => 'required|string',
            'kode_pos_rumah'      => 'nullable|string|max:10',
            'pendidikan_terakhir' => 'required|string',
            'nama_perusahaan'     => 'nullable|string',
            'jabatan'             => 'nullable|string',
            'alamat_kantor'       => 'nullable|string',
            'judul_skema'         => 'required|string',
            'nomor_skema'         => 'required|string',
            'tujuan_asesmen'      => 'required|in:Sertifikasi,Sertifikasi Ulang',
            'unit_kompetensi'     => 'required|array|min:1',
            'unit_kompetensi.*.kode_unit'     => 'required|string',
            'unit_kompetensi.*.judul_unit'    => 'required|string',
            'unit_kompetensi.*.jenis_standar' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi FR-APL-01 gagal',
                'errors'  => $validator->errors()
            ], 422);
        }

        $cert = Certification::create([
            'user_id'                  => $request->user()->id,
            'id_izin'                  => 'APL-' . strtoupper(Str::random(8)),
            'jenis_kelamin'            => $request->jenis_kelamin,
            'kebangsaan'               => $request->kebangsaan ?? 'Indonesia',
            'alamat_rumah'             => $request->alamat_rumah,
            'kode_pos_rumah'           => $request->kode_pos_rumah,
            'telp_rumah'               => $request->telp_rumah,
            'telp_kantor_pribadi'      => $request->telp_kantor_pribadi,
            'pendidikan_terakhir'      => $request->pendidikan_terakhir,
            'nama_perusahaan'          => $request->nama_perusahaan,
            'jabatan'                  => $request->jabatan,
            'alamat_kantor'            => $request->alamat_kantor,
            'kode_pos_kantor'          => $request->kode_pos_kantor,
            'telp_perusahaan'          => $request->telp_perusahaan,
            'fax_perusahaan'           => $request->fax_perusahaan,
            'email_perusahaan'         => $request->email_perusahaan,
            'judul_skema'              => $request->judul_skema,
            'nomor_skema'              => $request->nomor_skema,
            'tujuan_asesmen'           => $request->tujuan_asesmen,
            'unit_kompetensi'          => $request->unit_kompetensi,
            'bukti_persyaratan_dasar'  => $request->bukti_persyaratan_dasar,
            'bukti_kompetensi_relevan' => $request->bukti_kompetensi_relevan,
            'rekomendasi_lsp'          => $request->rekomendasi_lsp,
            'catatan_lsp'              => $request->catatan_lsp,
            'nama_admin_lsp'           => $request->nama_admin_lsp,
            'nik_admin_lsp'            => $request->nik_admin_lsp,
            'status'                   => 'Pemohon',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data FR-APL-01 berhasil disimpan dengan status Pemohon.',
            'data'    => $cert
        ], 201);
        
    }

    public function downloadPdf($id, $jenis_dokumen)
    {
        try {
            $cert = Certification::with('user')->findOrFail($id);
            $user = $cert->user;

            $daftarTemplate = [
                'APL01' => storage_path('app/templates/FR_APL_01.pdf'),
                'IA02'  => storage_path('app/templates/FR_IA_02.pdf'),
                'IA03'  => storage_path('app/templates/FR_IA_03.pdf'),
                'IA05'  => storage_path('app/templates/FR_IA_05.pdf'),
                'IA06'  => storage_path('app/templates/FR_IA_06.pdf'),
                'IA07'  => storage_path('app/templates/FR_IA_07.pdf'),
            ];

            $templatePath = $daftarTemplate[strtoupper($jenis_dokumen)] ?? null;
            if (!$templatePath || !file_exists($templatePath)) {
                return response()->json(['message' => 'File template PDF tidak ditemukan di: ' . $templatePath], 404);
            }

            // Ubah jadi true sementara jika ingin melihat garis penggaris merah (mm) di Halaman 2
            $debugGrid = false;

            $pdf = new Fpdi();
            $pdf->SetAutoPageBreak(false);
            $pageCount = $pdf->setSourceFile($templatePath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);

                if ($debugGrid) {
                    $pdf->SetDrawColor(230, 230, 230);
                    $pdf->SetFont('Helvetica', '', 6);
                    $pdf->SetTextColor(255, 0, 0);
                    for ($x = 10; $x < 210; $x += 10) {
                        $pdf->Line($x, 0, $x, 297);
                        $pdf->Text($x + 1, 6, (string) $x);
                    }
                    for ($y = 10; $y < 297; $y += 10) {
                        $pdf->Line(0, $y, 210, $y);
                        $pdf->Text(2, $y - 1, (string) $y);
                    }
                }

                $pdf->SetFont('Helvetica', '', 10);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetDrawColor(0, 0, 0);
                $pdf->SetFillColor(255, 255, 255);
                $pdf->SetLineWidth(0.2);

                // =========================================================================
                // HALAMAN 1: BAGIAN 1 (DATA PRIBADI & PEKERJAAN) + BAGIAN 2 (SKEMA)
                // =========================================================================
                if ($pageNo === 1) {
                    // --- a. Data Pribadi ---
                    $pdf->SetXY(77, 59);
                    $pdf->Write(0, (string) ($user->fullname ?? $user->name));

                    $pdf->SetXY(77, 66.5);
                    $pdf->Write(0, (string) (($user->place ?? '-') . ', ' . ($user->date_of_birth ?? '-')));

                    // Jenis Kelamin: Coret pilihan yang TIDAK sesuai
                $jk = strtolower(trim((string) $cert->jenis_kelamin));
                $pdf->SetDrawColor(0, 0, 0);
                $pdf->SetLineWidth(0.5);

                if (in_array($jk, ['laki-laki', 'laki', 'pria', 'l'])) {
                    // Coret "Wanita-*"
                    $pdf->Line(93.5, 74.8, 107.5, 74.8);
                } elseif (in_array($jk, ['wanita', 'perempuan', 'p', 'w'])) {
                    // Coret "Laki-laki"
                    $pdf->Line(76.5, 74.8, 91.5, 74.8);
                }

                $pdf->SetLineWidth(0.2);

                    $pdf->SetXY(77, 81.5);
                    $pdf->Write(0, (string) ($cert->kebangsaan ?? 'Indonesia'));

                    $pdf->SetXY(77, 89.5);
                    $pdf->Write(0, (string) ($cert->alamat_rumah ?? '-'));

                    $pdf->SetXY(144, 96.5);
                    $pdf->Write(0, (string) ($cert->kode_pos_rumah ?? '-'));

                    $pdf->SetXY(92, 104);
                    $pdf->Write(0, (string) ($cert->telp_rumah ?? '-'));

                    $pdf->SetXY(140, 104);
                    $pdf->Write(0, (string) ($cert->telp_kantor_pribadi ?? '-'));

                    $pdf->SetXY(87, 111.5);
                    $pdf->Write(0, (string) ($user->phone ?? '-'));

                    $pdf->SetXY(140, 111.5);
                    $pdf->Write(0, (string) ($user->email ?? '-'));

                    $pdf->SetXY(77, 121);
                    $pdf->Write(0, (string) ($cert->pendidikan_terakhir ?? '-'));

                    // --- b. Data Pekerjaan Sekarang ---
                    // Dinaikkan ke Y=139 agar duduk rapi di atas garis pertama (tidak menabrak garis)
                    $pdf->SetXY(78, 139);
                    $pdf->MultiCell(108, 7.5, (string) ($cert->nama_perusahaan ?? '-'), 0, 'L');

                    $pdf->SetXY(78, 157.5);
                    $pdf->Write(0, (string) ($cert->jabatan ?? '-'));

                    $pdf->SetXY(78, 166);
                    $pdf->Write(0, (string) ($cert->alamat_kantor ?? '-'));

                    $pdf->SetXY(144, 172.5);
                    $pdf->Write(0, (string) ($cert->kode_pos_kantor ?? '-'));

                    $pdf->SetXY(92, 180);
                    $pdf->Write(0, (string) ($cert->telp_perusahaan ?? '-'));

                    $pdf->SetXY(136, 180);
                    $pdf->Write(0, (string) ($cert->fax_perusahaan ?? '-'));

                    $pdf->SetXY(93, 188.5);
                    $pdf->Write(0, (string) ($cert->email_perusahaan ?? '-'));

                    // --- Bagian 2: Data Sertifikasi ---
                    // --- Bagian 2: Data Sertifikasi (Halaman 1) ---
                // Masking tepat di dalam batas sel tanpa menyentuh garis pembatas tabel
                if (!empty($cert->judul_skema)) {
                    $pdf->Rect(82.5, 224.8, 107.5, 9.7, 'F');
                    $pdf->SetFont('Helvetica', '', 9);
                    $pdf->SetXY(84, 225.8);
                    $pdf->MultiCell(104, 4.4, strtoupper((string) $cert->judul_skema), 0, 'L');
                }

                if (!empty($cert->nomor_skema)) {
                    $pdf->Rect(82.5, 235.5, 107.5, 6.0, 'F');
                    $pdf->SetFont('Helvetica', '', 10);
                    $pdf->SetXY(84, 238.5);
                    $pdf->Write(0, (string) $cert->nomor_skema);
                }

                if ($cert->tujuan_asesmen === 'Sertifikasi Ulang') {
                    $pdf->Rect(85.8, 247.5, 4, 4, 'DF');
                    $pdf->SetFont('ZapfDingbats', '', 10);
                    $pdf->SetXY(120.5, 249.5);
                    $pdf->Write(0, '4');
                    $pdf->SetFont('Helvetica', '', 10);
                }
            }

            // =========================================================================
            // HALAMAN 2: DAFTAR UNIT KOMPETENSI & BAGIAN 3 (BUKTI KELENGKAPAN PEMOHON)
            // =========================================================================
            if ($pageNo === 2) {
                $units = $cert->unit_kompetensi ?? [];
                if (is_array($units) && count($units) > 0) {
                    $pdf->Rect(28.5, 40.8, 165.0, 50.0, 'F');
                    $pdf->SetFont('Helvetica', '', 8.5);

                    $startY = 40.5;
                    $rowH = 9.5;

                    foreach (array_slice($units, 0, 5) as $idx => $unit) {
                        $y = $startY + ($idx * $rowH);

                        $pdf->Rect(29.4, $y, 10.5, $rowH);
                        $pdf->Rect(39.9, $y, 34.4, $rowH);
                        $pdf->Rect(74.3, $y, 80.5, $rowH);
                        $pdf->Rect(154.8, $y, 37.6, $rowH);

                        $pdf->SetXY(29.4, $y + 2.8);
                        $pdf->Cell(10.5, 4, ($idx + 1) . '.', 0, 0, 'C');

                        $pdf->SetXY(41.0, $y + 2.8);
                        $pdf->Cell(32.5, 4, (string) ($unit['kode_unit'] ?? '-'), 0, 0, 'L');

                        $judulUnit = (string) ($unit['judul_unit'] ?? '-');
                        $yJudul = $pdf->GetStringWidth($judulUnit) > 77.5 ? $y + 1.3 : $y + 2.8;
                        $pdf->SetXY(75.5, $yJudul);
                        $pdf->MultiCell(78.0, 3.5, $judulUnit, 0, 'L');

                        $pdf->SetXY(154.8, $y + 2.8);
                        $pdf->Cell(37.6, 4, (string) ($unit['jenis_standar'] ?? 'SKKNI'), 0, 0, 'C');
                    }
                }

                $syaratDasar = $cert->bukti_persyaratan_dasar ?? [];
                $yBarisSyarat = [
                    1 => 140.5,
                    2 => 152.5,
                    3 => 166.5,
                    4 => 178.0,
                ];

                if (is_array($syaratDasar)) {
                    $pdf->SetFont('ZapfDingbats', '', 10);
                    foreach ($syaratDasar as $idx => $item) {
                        $no = $item['no'] ?? ($idx + 1);
                        if (!isset($yBarisSyarat[$no])) continue;

                        $status = strtolower((string) ($item['status'] ?? ''));
                        $yPos = $yBarisSyarat[$no];

                        if ($status === 'memenuhi_syarat') {
                            $pdf->SetXY(138.5, $yPos);
                            $pdf->Write(0, '4');
                        } elseif ($status === 'tidak_memenuhi_syarat') {
                            $pdf->SetXY(158.0, $yPos);
                            $pdf->Write(0, '4');
                        } elseif ($status === 'tidak_ada') {
                            $pdf->SetXY(176.5, $yPos);
                            $pdf->Write(0, '4');
                        }
                    }
                }

                $buktiRelevan = $cert->bukti_kompetensi_relevan ?? [];
                $startYRelevan = 220.0;
                $stepYRelevan = 8.3;

                if (is_array($buktiRelevan)) {
                    foreach (array_slice($buktiRelevan, 0, 5) as $idx => $bukti) {
                        $yPos = $startYRelevan + ($idx * $stepYRelevan);

                        $pdf->SetFont('Helvetica', '', 9);
                        $pdf->SetXY(40, $yPos);
                        $pdf->Write(0, (string) ($bukti['rincian'] ?? '-'));

                        $status = strtolower((string) ($bukti['status'] ?? 'ada'));
                        $pdf->SetFont('ZapfDingbats', '', 10);
                        if ($status === 'ada') {
                            $pdf->SetXY(166, $yPos);
                            $pdf->Write(0, '4');
                        } elseif ($status === 'tidak_ada') {
                            $pdf->SetXY(182, $yPos);
                            $pdf->Write(0, '4');
                        }
                    }
                }
            }
            // =========================================================================
            // HALAMAN 3: REKOMENDASI LSP, CATATAN, PEMOHON, & ADMIN LSP
            // =========================================================================
            if ($pageNo === 3) {
                $pdf->SetLineWidth(0.45);
                if ($cert->rekomendasi_lsp === 'Diterima') {
                    // Centang tepat di dalam kotak
                    $pdf->SetFont('ZapfDingbats', '', 10);
                    $pdf->SetXY(31.8, 58.3);
                    $pdf->Write(0, '4');
                    // Coret tepat di tengah teks "Tidak diterima*"
                    $pdf->Line(54.5, 58.6, 80.5, 58.6);
                } elseif ($cert->rekomendasi_lsp === 'Tidak diterima') {
                    $pdf->SetFont('ZapfDingbats', '', 10);
                    $pdf->SetXY(31.8, 58.3);
                    $pdf->Write(0, '4');
                    // Coret tepat di tengah teks "Diterima/"
                    $pdf->Line(39.5, 58.6, 53.5, 58.6);
                }
                $pdf->SetLineWidth(0.2);

                $pdf->SetFont('Helvetica', '', 9.5);

                // Catatan LSP
                $pdf->SetXY(32, 88);
                $pdf->MultiCell(82, 5, (string) ($cert->catatan_lsp ?? '-'), 0, 'L');

                // --- Kolom Pemohon ---
                $pdf->SetXY(148, 48.5);
                $pdf->Write(0, (string) ($user->fullname ?? $user->name));

                if ($cert->ttd_pemohon_path && file_exists(storage_path('app/public/' . $cert->ttd_pemohon_path))) {
                    $pdf->Image(storage_path('app/public/' . $cert->ttd_pemohon_path), 153, 55, 26);
                }
                $pdf->SetXY(148, 72);
                $pdf->Write(0, $cert->created_at ? $cert->created_at->format('d-m-Y') : date('d-m-Y'));

                // --- Kolom Admin LSP ---
                $pdf->SetXY(148, 87.5);
                $pdf->Write(0, (string) ($cert->nama_admin_lsp ?? '-'));

                // NIK LSP dinaikkan sedikit agar tidak menyentuh garis bawah sel
                $pdf->SetXY(148, 93.0);
                $pdf->Write(0, (string) ($cert->nik_admin_lsp ?? '-'));

                if ($cert->ttd_admin_path && file_exists(storage_path('app/public/' . $cert->ttd_admin_path))) {
                    $pdf->Image(storage_path('app/public/' . $cert->ttd_admin_path), 153, 100, 26);
                }
                $pdf->SetXY(148, 115);
                $pdf->Write(0, $cert->rekomendasi_lsp ? $cert->updated_at->format('d-m-Y') : '-');
            }
            }

            return response($pdf->Output('S'), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . strtoupper($jenis_dokumen) . '_' . $cert->id_izin . '.pdf"');
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }
}
