<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SignatureController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            // frontend mengirim string base64 dari canvas TTD
            'signature' => 'required|string', 
        ]);

        $user = $request->user();
        $base64Image = $request->signature;

        // Pisahkan metadata dan data image base64
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
            $base64Image = substr($base64Image, strpos($base64Image, ',') + 1);
            $type = strtolower($type[1]); // png, jpg, jpeg

            if (!in_array($type, ['jpg', 'jpeg', 'png'])) {
                return response()->json(['message' => 'Format gambar tidak valid'], 400);
            }

            $base64Image = str_replace(' ', '+', $base64Image);
            $image_data = base64_decode($base64Image);

            if ($image_data === false) {
                return response()->json(['message' => 'Gagal decode base64'], 400);
            }
        } else {
            return response()->json(['message' => 'Data TTD bukan base64 image yang valid'], 400);
        }

        // Hapus TTD lama jika ada agar storage tidak bengkak
        if ($user->signature_path && Storage::disk('public')->exists($user->signature_path)) {
            Storage::disk('public')->delete($user->signature_path);
        }

        // Simpan file TTD baru
        $fileName = 'signatures/' . $user->id . '_' . time() . '.' . $type;
        Storage::disk('public')->put($fileName, $image_data);

        // Update database user
        $user->update(['signature_path' => $fileName]);

        return response()->json([
            'success' => true,
            'message' => 'Tanda tangan berhasil disimpan',
            'data'    => [
                'signature_url' => asset('storage/' . $fileName),
            ]
        ], 200);
    }
}