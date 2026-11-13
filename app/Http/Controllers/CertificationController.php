<?php

namespace App\Http\Controllers;

use App\Models\Certification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CertificationController extends Controller
{
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
            'skema_sertifikasi' => 'required|string|max:255',
            'jenjang'           => 'required|string|max:50',
            'jenis_permohonan'  => 'nullable|in:mitra,langsung',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors()
            ], 422);
        }

        $currentHour = (int) now()->format('H');
        $jenisPermohonan = $request->jenis_permohonan ?? ($currentHour >= 18 ? 'langsung' : 'mitra');

        $certification = Certification::create([
            'user_id'           => $request->user()->id,
            'id_izin'           => 'IZIN-' . strtoupper(Str::random(8)),
            'jenis_permohonan'  => $jenisPermohonan,
            'skema_sertifikasi' => $request->skema_sertifikasi,
            'jenjang'           => $request->jenjang,
            'status'            => 'Cek',
            'status_updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permohonan berhasil dibuat dengan status Cek.',
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
}
