<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->latest();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('fullname', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'success' => true,
            'data'    => $query->get(),
        ], 200);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $user,
        ], 200);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'          => 'sometimes|required|string|max:255',
            'fullname'      => 'sometimes|required|string|max:255',
            'place'         => 'sometimes|required|string|max:255',
            'date_of_birth' => 'sometimes|required|date',
            'phone'         => 'sometimes|required|string|max:20',
            'email'         => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role'          => 'sometimes|required|in:user,admin,super_admin',
            'password'      => 'nullable|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi update user gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if ($request->user()->id === $user->id && $request->filled('role') && $request->role !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Super Admin tidak dapat menurunkan role akunnya sendiri.',
            ], 400);
        }

        $data = $request->only([
            'name',
            'fullname',
            'place',
            'date_of_birth',
            'phone',
            'email',
            'role',
        ]);

        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Data akun berhasil diperbarui',
            'data'    => $user->fresh(),
        ], 200);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($request->user()->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus akun yang sedang digunakan login.',
            ], 400);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil dihapus beserta seluruh token aksesnya.',
        ], 200);
    }
}