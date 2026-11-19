<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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
            'name'          => 'required|string|max:255',
            'fullname'      => 'required|string|max:255',
            'place'         => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'phone'         => 'required|string|max:20',
            'email'         => 'required|string|email|max:255|unique:users,email',
            'password'      => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi registrasi ' . $role . ' gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name'          => $request->name,
            'fullname'      => $request->fullname,
            'place'         => $request->place,
            'date_of_birth' => $request->date_of_birth,
            'phone'         => $request->phone,
            'email'         => $request->email,
            'role'          => $role,
            'password'      => $request->password,
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
