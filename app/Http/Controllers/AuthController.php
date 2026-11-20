<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage; // Tambahkan ini untuk akses Storage

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'       => 'required|email',
            'password'    => 'required|string',
            'device_name' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah',
            ], 401);
        }

        $deviceName = $request->device_name ?? 'auth_token';
        $token = $user->createToken($deviceName, [$user->role])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil sebagai ' . $user->role,
            'data'    => [
                'user'       => $user,
                'token'      => $token,
                'token_type' => 'Bearer',
            ],
        ], 200);
    }

    public function registerUser(Request $request): JsonResponse
    {
        return $this->createAccountByRole($request, 'user', true);
    }

    public function registerAdmin(Request $request): JsonResponse
    {
        return $this->createAccountByRole($request, 'admin', false);
    }

    public function registerSuperAdmin(Request $request): JsonResponse
    {
        return $this->createAccountByRole($request, 'super_admin', false);
    }

    public function historyLogin(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $request->user(),
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil',
        ], 200);
    }

    private function createAccountByRole(Request $request, string $role, bool $issueToken): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'             => 'required|string|max:255',
            'fullname'         => 'required|string|max:255',
            'place'            => 'required|string|max:255',
            'date_of_birth'    => 'required|date',
            'phone'            => 'required|string|max:20',
            'email'            => 'required|string|email|max:255|unique:users,email',
            'password'         => 'required|string|min:8|confirmed',
            // Tambahan validasi untuk TTD
            'signature'        => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'signature_base64' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi registrasi ' . $role . ' gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // --- PROSES TANDA TANGAN ---
        $signaturePath = null;

        // 1. Jika dikirim dari React Canvas via Base64
        if ($request->filled('signature_base64')) {
            $image_parts = explode(";base64,", $request->signature_base64);
            
            if (count($image_parts) == 2) {
                $image_base64 = base64_decode($image_parts[1]);
                $signaturePath = 'signatures/sig_' . time() . '_' . uniqid() . '.png';
                Storage::disk('public')->put($signaturePath, $image_base64);
            }
        } 
        // 2. Jika dikirim via File Upload biasa
        else if ($request->hasFile('signature')) {
            $signaturePath = $request->file('signature')->store('signatures', 'public');
        }
        // ---------------------------

        $user = User::create([
            'name'           => $request->name,
            'fullname'       => $request->fullname,
            'place'          => $request->place,
            'date_of_birth'  => $request->date_of_birth,
            'phone'          => $request->phone,
            'email'          => $request->email,
            'role'           => $role,
            'password'       => $request->password, // Mutator Hash otomatis bekerja kalau sudah di-set di Model, jika belum, gunakan Hash::make()
            'signature_path' => $signaturePath, // Simpan path TTD ke database
        ]);

        $payload = ['user' => $user];

        if ($issueToken) {
            $payload['token'] = $user->createToken('auth_token', [$role])->plainTextToken;
            $payload['token_type'] = 'Bearer';
        }

        return response()->json([
            'success' => true,
            'message' => 'Registrasi ' . $role . ' berhasil',
            'data'    => $payload,
        ], 201);
    }
}