<?php

namespace App\Http\Controllers;

use App\Models\Certification;
use App\Models\User;
use App\Services\WhatsAppNotifier;
use App\Support\CertificationFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;

class CertificationController extends Controller
{
    public function __construct(private WhatsAppNotifier $wa)
    {
    }

    // ---------------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------------

    private function mayAccess(User $user, Certification $cert): bool
    {
        if (in_array($user->role, ['admin', 'super_admin', 'asesor'], true)) {
            return true;
        }
        if ($user->role === 'mitra') {
            return (int) $cert->mitra_id === (int) $user->id;
        }
        return (int) $cert->user_id === (int) $user->id;
    }

    /** Ambil permohonan dan pastikan pengguna berhak mengaksesnya (cegah akses data orang lain). */
    private function findAccessible(Request $request, $id): Certification
    {
        $cert = Certification::findOrFail($id);
        abort_unless($this->mayAccess($request->user(), $cert), 403, 'Anda tidak berhak mengakses permohonan ini.');
        return $cert;
    }

    private function fail(string $message, int $code = 400, array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['success' => false, 'message' => $message], $extra), $code);
    }

    private function ok(string $message, $data = null, int $code = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $code);
    }

    /** Pastikan status saat ini sesuai dan transisi ke status tujuan memang diizinkan oleh flowchart. */
    private function guard(Certification $cert, string $expectedStatus, string $to): ?JsonResponse
    {
        if ($cert->status !== $expectedStatus) {
            return $this->fail("Aksi ini hanya untuk permohonan berstatus {$expectedStatus}. Status saat ini: {$cert->status}.", 409);
        }
        if (! CertificationFlow::canMove($cert->status, $to)) {
            return $this->fail("Transisi {$cert->status} -> {$to} tidak diizinkan.", 409);
        }
        return null;
    }

    /** Terapkan batas 1x24 jam (lihat PDF kolom Waktu). Mengembalikan response bila sudah kedaluwarsa. */
    private function guardDeadline(Certification $cert): ?JsonResponse
    {
        $limit = CertificationFlow::DEADLINE_HOURS[$cert->status] ?? null;
        $since = $cert->status_updated_at ?? $cert->updated_at;

        if ($limit !== null && $since && $since->diffInHours(now(), true) >= $limit) {
            $target = CertificationFlow::EXPIRED_TARGET[$cert->status] ?? 'Batal';
            $cert->update([
                'status'            => $target,
                'batal_alasan'      => "Melewati batas {$limit} jam pada status {$cert->status}",
                'status_updated_at' => now(),
            ]);
            return $this->fail(
                $target === 'Assesor_No'
                    ? 'Batas 1x24 jam terlewat. Peserta dinyatakan Belum Kompeten.'
                    : 'Batas 1x24 jam terlewat. Permohonan dibatalkan, silakan mohon dari baru.',
                410
            );
        }
        return null;
    }

    private function afterMitraCutoff(): bool
    {
        return now('Asia/Jakarta')->format('H:i') > CertificationFlow::MITRA_CUTOFF;
    }

    // ---------------------------------------------------------------------
    // Daftar
    // ---------------------------------------------------------------------

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Certification::with('user:id,name,fullname,email,phone,signature_path')->latest();

        if (in_array($user->role, ['admin', 'super_admin', 'asesor'], true)) {
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
        } elseif ($user->role === 'mitra') {
            $query->where('mitra_id', $user->id);
        } else {
            $query->where('user_id', $user->id);
        }

        return response()->json(['success' => true, 'data' => $query->get()], 200);
    }

    // ---------------------------------------------------------------------
    // Tahap 1: Permohonan (Pemohon langsung atau Mitra via portal PU) -> status "Cek"
    // ---------------------------------------------------------------------

    public function store(Request $request)
    {
        $user    = $request->user();
        $isMitra = $user->role === 'mitra';

        $validator = Validator::make($request->all(), [
            'judul_skema'  => 'required|string|max:255',
            'nomor_skema'  => 'nullable|string|max:255',
            'jenjang'      => 'nullable|string|max:255',
            'jenis_kelamin' => 'nullable|string|max:50',
            // Mitra wajib mengirim Id Izin dari portal PU dan menunjuk pemohonnya
            'id_izin'      => ($isMitra ? 'required' : 'nullable') . '|string|max:100|unique:certifications,id_izin',
            'user_id'      => ($isMitra ? 'required' : 'nullable') . '|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->only([
            'jenis_permohonan', 'jenjang', 'jenis_kelamin', 'kebangsaan', 'alamat_rumah', 'kode_pos_rumah',
            'telp_rumah', 'telp_kantor_pribadi', 'pendidikan_terakhir', 'nama_perusahaan', 'jabatan',
            'alamat_kantor', 'kode_pos_kantor', 'telp_perusahaan', 'fax_perusahaan', 'email_perusahaan',
            'nomor_skema', 'tujuan_asesmen', 'unit_kompetensi', 'bukti_persyaratan_dasar', 'bukti_kompetensi_relevan',
        ]);

        $data['judul_skema']       = $request->judul_skema;
        $data['status']            = 'Cek';
        $data['status_updated_at'] = now();

        if ($isMitra) {
            $data['mitra_id']         = $user->id;
            $data['user_id']          = $request->user_id;
            $data['id_izin']          = $request->id_izin;
            $data['portal_input_at']  = now();
            // Mitra hanya diberi waktu input 00:00 - 18:00; lewat dari itu diakui Pemohon langsung
            $data['jenis_permohonan'] = $this->afterMitraCutoff() ? 'langsung' : 'mitra';
        } else {
            $data['user_id']          = $user->id;
            $data['id_izin']          = $request->id_izin ?: 'APL-' . strtoupper(Str::random(8));
            $data['jenis_permohonan'] = 'langsung';
        }

        $cert = Certification::create($data);

        return $this->ok('Permohonan sertifikasi dibuat dengan status Cek.', $cert, 201);
    }

    /** Mitra meng-"Get" data di SIPP memakai 5 data yang ditetapkan SIPP. */
    public function getFromSipp(Request $request, $id)
    {
        $cert = $this->findAccessible($request, $id);

        if ($e = $this->guard($cert, 'Cek', 'Pemohon')) {
            return $e;
        }

        $validator = Validator::make($request->all(), [
            'nomor_registrasi' => 'required|string|max:100',
            'sipp_data'        => 'required|array|min:5', // PDF: "memasukkan 5 data yang sudah ditetapkan di SIPP"
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $update = [
            'nomor_registrasi'  => $request->nomor_registrasi,
            'sipp_data'         => $request->sipp_data,
            'sipp_get_at'       => now(),
            'status_updated_at' => now(),
        ];

        // Get setelah 18:01 = Pemohon langsung
        if ($this->afterMitraCutoff()) {
            $update['jenis_permohonan'] = 'langsung';
        }

        $cert->update($update);

        return $this->ok('Data berhasil di-Get di SIPP. Status tetap Cek; lanjutkan isi APL-01 dan APL-02.', $cert);
    }

    // ---------------------------------------------------------------------
    // Tahap 2: APL-01/APL-02 -> "Pemohon" -> approve pemohon -> "Apl_01"
    // ---------------------------------------------------------------------

    public function submitApl(Request $request, $id)
    {
        $cert = $this->findAccessible($request, $id);

        if ($e = $this->guard($cert, 'Cek', 'Pemohon')) {
            return $e;
        }

        $validator = Validator::make($request->all(), [
            'apl_01_data' => 'required|array',
            'apl_02_data' => 'required|array',
        ]);

        if ($validator->fails()) {
            return $this->fail('Data APL 01 dan APL 02 wajib diisi berupa objek/array JSON.', 422, ['errors' => $validator->errors()]);
        }

        // Mitra yang sudah input portal wajib melakukan Get SIPP lebih dulu
        if ($cert->jenis_permohonan === 'mitra' && empty($cert->sipp_get_at)) {
            return $this->fail('Data belum di-Get di SIPP. Lakukan Get SIPP sebelum mengisi APL.', 409);
        }

        $cert->update([
            'apl_01_data'       => $request->apl_01_data,
            'apl_02_data'       => $request->apl_02_data,
            'status'            => 'Pemohon',
            'status_updated_at' => now(),
        ]);

        $cert->loadMissing('user');
        $this->wa->send($cert->user?->phone, "Mohon setujui pernyataan kebenaran data APL-01 dan APL-02 ({$cert->id_izin}) dalam 1x24 jam.");

        return $this->ok('APL-01 dan APL-02 tersimpan. Status Pemohon; notifikasi WA dikirim ke pemohon.', $cert);
    }

    public function approveAplByPemohon(Request $request, $id)
    {
        $cert = Certification::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();

        if ($e = $this->guard($cert, 'Pemohon', 'Apl_01')) {
            return $e;
        }
        if ($e = $this->guardDeadline($cert)) {
            return $e;
        }

        $cert->update([
            'is_approved_by_pemohon' => true,
            'approved_pemohon_at'    => now(),
            'status'                 => 'Apl_01',
            'status_updated_at'      => now(),
        ]);

        return $this->ok('Data APL-01 dan APL-02 disetujui pemohon. Status Apl_01, menunggu pemeriksaan Admin LSP.', $cert);
    }

    // ---------------------------------------------------------------------
    // Tahap 3: Admin LSP memeriksa APL-01 -> "Peserta"
    // ---------------------------------------------------------------------

    public function reviewApl01(Request $request, $id)
    {
        $admin = $request->user();

        $validator = Validator::make($request->all(), [
            'rekomendasi_lsp'          => 'required|in:Diterima,Tidak diterima',
            'catatan_lsp'              => 'nullable|string',
            'nama_admin_lsp'           => 'nullable|string|max:255',
            'nik_admin_lsp'            => 'nullable|string|max:100',
            'bukti_persyaratan_dasar'  => 'nullable|array',
            'bukti_kompetensi_relevan' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->fail('Validasi review Admin LSP gagal.', 422, ['errors' => $validator->errors()]);
        }

        if (empty($admin->signature_path)) {
            return $this->fail('Akun Admin belum memiliki tanda tangan (signature_path kosong).');
        }

        $cert = Certification::findOrFail($id);
        $to   = $request->rekomendasi_lsp === 'Diterima' ? 'Peserta' : 'Batal';

        if ($e = $this->guard($cert, 'Apl_01', $to)) {
            return $e;
        }

        $update = [
            'rekomendasi_lsp'   => $request->rekomendasi_lsp,
            'catatan_lsp'       => $request->catatan_lsp,
            'nama_admin_lsp'    => $request->nama_admin_lsp ?? $admin->fullname ?? $admin->name,
            'nik_admin_lsp'     => $request->nik_admin_lsp ?? $admin->nik ?? '-',
            'ttd_admin_path'    => $admin->signature_path,
            'status'            => $to,
            'status_updated_at' => now(),
        ];

        if ($to === 'Batal') {
            $update['batal_alasan'] = $request->catatan_lsp ?: 'Ditolak Admin LSP saat pemeriksaan APL-01';
        }
        if ($request->has('bukti_persyaratan_dasar')) {
            $update['bukti_persyaratan_dasar'] = $request->bukti_persyaratan_dasar;
        }
        if ($request->has('bukti_kompetensi_relevan')) {
            $update['bukti_kompetensi_relevan'] = $request->bukti_kompetensi_relevan;
        }

        $cert->update($update);

        return $this->ok(
            $to === 'Peserta' ? 'APL-01 diterima. Status berubah menjadi Peserta.' : 'APL-01 tidak diterima. Permohonan dibatalkan.',
            $cert
        );
    }

    // ---------------------------------------------------------------------
    // Tahap 4: Tagihan & pembayaran: Peserta -> Tagih (otomatis 11:00) -> Lunas
    // ---------------------------------------------------------------------

    /** Admin mengatur nominal tagihan (opsional) sebelum/sesudah tagihan dibuat. */
    public function setInvoiceAmount(Request $request, $id)
    {
        $request->validate(['nominal_tagihan' => 'required|numeric|min:0']);

        $cert = Certification::findOrFail($id);

        if (! in_array($cert->status, ['Peserta', 'Tagih'], true)) {
            return $this->fail('Nominal hanya dapat diubah pada status Peserta atau Tagih.', 409);
        }

        $cert->update(['nominal_tagihan' => $request->nominal_tagihan]);

        return $this->ok('Nominal tagihan diperbarui.', $cert);
    }

    /** Pemohon/Mitra mengunggah bukti bayar. Status BELUM Lunas sampai admin mengonfirmasi. */
    public function submitPaymentProof(Request $request, $id)
    {
        $cert = $this->findAccessible($request, $id);

        if ($e = $this->guard($cert, 'Tagih', 'Lunas')) {
            return $e;
        }
        if ($e = $this->guardDeadline($cert)) {
            return $e;
        }

        $request->validate(['bukti_bayar' => 'required|string']);

        $cert->update(['bukti_bayar' => $request->bukti_bayar, 'bukti_bayar_at' => now()]);

        return $this->ok('Bukti pembayaran diterima, menunggu verifikasi Admin LSP.', $cert);
    }

    public function confirmPayment(Request $request, $id)
    {
        $cert = Certification::findOrFail($id);

        if ($e = $this->guard($cert, 'Tagih', 'Lunas')) {
            return $e;
        }
        if (empty($cert->bukti_bayar)) {
            return $this->fail('Bukti pembayaran belum diunggah.', 409);
        }

        $cert->update(['lunas_at' => now(), 'status' => 'Lunas', 'status_updated_at' => now()]);

        return $this->ok('Pembayaran terkonfirmasi. Status berubah menjadi Lunas.', $cert);
    }

    // ---------------------------------------------------------------------
    // Tahap 5: Jadwal (H+2 setelah lunas) -> Assesor -> Pra Asesmen
    // ---------------------------------------------------------------------

    public function scheduleAssessment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'nama_asesor'    => 'required|string|max:255',
            'no_reg_asesor'  => 'nullable|string|max:100',
            'jadwal_asesmen' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $cert = Certification::findOrFail($id);

        if ($e = $this->guard($cert, 'Lunas', 'Assesor')) {
            return $e;
        }

        $earliest = $cert->lunas_at->copy()->addDays(CertificationFlow::ASSESSMENT_DAYS_AFTER_PAYMENT)->startOfDay();
        if (now()->parse($request->jadwal_asesmen)->lt($earliest)) {
            return $this->fail('Asesmen dilaksanakan H+' . CertificationFlow::ASSESSMENT_DAYS_AFTER_PAYMENT . ' setelah lunas (paling cepat ' . $earliest->format('d-m-Y') . ').', 422);
        }

        $cert->update([
            'nama_asesor'       => $request->nama_asesor,
            'no_reg_asesor'     => $request->no_reg_asesor,
            'jadwal_asesmen'    => $request->jadwal_asesmen,
            'status'            => 'Assesor',
            'status_updated_at' => now(),
        ]);

        return $this->ok('Jadwal dibuat. Status berubah menjadi Assesor.', $cert);
    }

    /** Asesor melakukan pra-asesmen. Lolos -> Pra_Assesment (notif WA ke pemohon); tidak lolos -> Assesor_No. */
    public function setPreAssessment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'lolos'   => 'required|boolean',
            'catatan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $cert = Certification::with('user')->findOrFail($id);
        $user = $request->user();

        if ($user->role === 'asesor' && $cert->no_reg_asesor !== $user->no_reg_asesor) {
            return $this->fail('Permohonan ini bukan penugasan Anda.', 403);
        }

        $to = $request->boolean('lolos') ? 'Pra_Assesment' : 'Assesor_No';

        if ($e = $this->guard($cert, 'Assesor', $to)) {
            return $e;
        }

        $cert->update([
            'pra_asesmen_catatan' => $request->catatan,
            'status'              => $to,
            'status_updated_at'   => now(),
        ]);

        if ($to === 'Pra_Assesment') {
            $this->wa->send($cert->user?->phone, "Pra-asesmen {$cert->id_izin} lolos. Mohon setujui asesmen dan kerahasiaan dalam 1x24 jam.");
        }

        return $this->ok('Hasil pra-asesmen disimpan. Status: ' . $to . '.', $cert);
    }

    public function approveAssessmentByPeserta(Request $request, $id)
    {
        $cert = Certification::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();

        if ($e = $this->guard($cert, 'Pra_Assesment', 'Assesment')) {
            return $e;
        }
        if ($e = $this->guardDeadline($cert)) {
            return $e;
        }

        $validator = Validator::make($request->all(), ['kuesioner_data' => 'required|array']);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $cert->update([
            'kuesioner_data'    => $request->kuesioner_data,
            'is_agreed_asesmen' => true,
            'status'            => 'Assesment',
            'status_updated_at' => now(),
        ]);

        return $this->ok('Persetujuan asesmen dan kerahasiaan diterima. Status berubah menjadi Assesment.', $cert);
    }

    // ---------------------------------------------------------------------
    // Tahap 6: Keputusan akhir -> Assesor_Yes / Assesor_No -> SKK terbit
    // ---------------------------------------------------------------------

    public function submitFinalDecision(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'hasil'                => 'required|in:Assesor_Yes,Assesor_No',
            'catatan_asesor'       => 'nullable|string',
            'nomor_sertifikat_skk' => 'required_if:hasil,Assesor_Yes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $cert = Certification::findOrFail($id);
        $user = $request->user();

        if ($user->role === 'asesor' && $cert->no_reg_asesor !== $user->no_reg_asesor) {
            return $this->fail('Permohonan ini bukan penugasan Anda.', 403);
        }
        if ($e = $this->guard($cert, 'Assesment', $request->hasil)) {
            return $e;
        }

        $cert->update([
            'status'               => $request->hasil,
            'catatan_asesor'       => $request->catatan_asesor,
            'nomor_sertifikat_skk' => $request->hasil === 'Assesor_Yes' ? $request->nomor_sertifikat_skk : null,
            'sertifikat_terbit_at' => $request->hasil === 'Assesor_Yes' ? now() : null,
            'status_updated_at'    => now(),
        ]);

        return $this->ok(
            $request->hasil === 'Assesor_Yes'
                ? 'Peserta dinyatakan KOMPETEN (Assesor_Yes). SKK siap dicatat di SIPP dan portal PU.'
                : 'Peserta dinyatakan BELUM KOMPETEN (Assesor_No).',
            $cert
        );
    }

    // ---------------------------------------------------------------------
    // Form APL-01 lengkap (pemohon langsung), status awal tetap "Cek"
    // ---------------------------------------------------------------------

    public function submitApl01(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jenis_kelamin'                   => 'required|in:Laki-laki,Wanita',
            'kebangsaan'                      => 'nullable|string',
            'alamat_rumah'                    => 'required|string',
            'kode_pos_rumah'                  => 'nullable|string|max:10',
            'pendidikan_terakhir'             => 'required|string',
            'nama_perusahaan'                 => 'nullable|string',
            'jabatan'                         => 'nullable|string',
            'alamat_kantor'                   => 'nullable|string',
            'judul_skema'                     => 'required|string',
            'nomor_skema'                     => 'required|string',
            'tujuan_asesmen'                  => 'required|in:Sertifikasi,Sertifikasi Ulang',
            'unit_kompetensi'                 => 'required|array|min:1',
            'unit_kompetensi.*.kode_unit'     => 'required|string',
            'unit_kompetensi.*.judul_unit'    => 'required|string',
            'unit_kompetensi.*.jenis_standar' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->fail('Validasi FR-APL-01 gagal', 422, ['errors' => $validator->errors()]);
        }

        $cert = Certification::create(array_merge(
            $request->only([
                'jenis_kelamin', 'alamat_rumah', 'kode_pos_rumah', 'telp_rumah', 'telp_kantor_pribadi',
                'pendidikan_terakhir', 'nama_perusahaan', 'jabatan', 'alamat_kantor', 'kode_pos_kantor',
                'telp_perusahaan', 'fax_perusahaan', 'email_perusahaan', 'judul_skema', 'nomor_skema',
                'tujuan_asesmen', 'unit_kompetensi', 'bukti_persyaratan_dasar', 'bukti_kompetensi_relevan',
            ]),
            [
                'user_id'           => $request->user()->id,
                'id_izin'           => 'APL-' . strtoupper(Str::random(8)),
                'kebangsaan'        => $request->kebangsaan ?? 'Indonesia',
                'jenis_permohonan'  => 'langsung',
                'status'            => 'Cek',
                'status_updated_at' => now(),
            ]
        ));

        return $this->ok('Data FR-APL-01 berhasil disimpan dengan status Cek.', $cert, 201);
    }

    public function downloadPdf(Request $request, $id, $jenis_dokumen)
    {
        try {
            $cert = Certification::with('user')->findOrFail($id);
            abort_unless($this->mayAccess($request->user(), $cert), 403, 'Anda tidak berhak mengunduh dokumen ini.');
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
                return response()->json(['message' => 'Template PDF untuk dokumen ' . strtoupper($jenis_dokumen) . ' belum tersedia.'], 404);
            }

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

                    // --- Bagian 2: Data Sertifikasi (Halaman 1) ---
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
                        $pdf->SetFont('ZapfDingbats', '', 10);
                        $pdf->SetXY(31.8, 58.3);
                        $pdf->Write(0, '4');
                        $pdf->Line(54.5, 58.6, 80.5, 58.6);
                    } elseif ($cert->rekomendasi_lsp === 'Tidak diterima') {
                        $pdf->SetFont('ZapfDingbats', '', 10);
                        $pdf->SetXY(31.8, 58.3);
                        $pdf->Write(0, '4');
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

                    // INJEKSI TTD PEMOHON DARI USERS TABLE (signature_path)
                    if (!empty($user->signature_path) && file_exists(storage_path('app/public/' . $user->signature_path))) {
                        $pdf->Image(storage_path('app/public/' . $user->signature_path), 156, 58, 26);
                    }

                    $pdf->SetXY(160, 72);
                    $pdf->Write(0, $cert->created_at ? $cert->created_at->format('d-m-Y') : date('d-m-Y'));

                    // --- Kolom Admin LSP ---
                    $pdf->SetXY(148, 87.5);
                    $pdf->Write(0, (string) ($cert->nama_admin_lsp ?? '-'));

                    $pdf->SetXY(148, 93.0);
                    $pdf->Write(0, (string) ($cert->nik_admin_lsp ?? '-'));

                    // INJEKSI TTD ADMIN (Jika ada di tabel certifications)
                    if (!empty($cert->ttd_admin_path) && file_exists(storage_path('app/public/' . $cert->ttd_admin_path))) {
                        $pdf->Image(storage_path('app/public/' . $cert->ttd_admin_path), 156, 100, 26);
                    }

                    $pdf->SetXY(160, 115);
                    $pdf->Write(0, $cert->rekomendasi_lsp ? $cert->updated_at->format('d-m-Y') : '-');
                }
            }

            return response($pdf->Output('S'), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . strtoupper($jenis_dokumen) . '_' . $cert->id_izin . '.pdf"');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface | \Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw $e; // biarkan 403/404 diproses handler Laravel
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat dokumen PDF.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
