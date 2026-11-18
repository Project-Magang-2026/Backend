<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Router;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /**
     * POST /api/admin/routers
     * Menambah router baru (api_password otomatis terenkripsi via mutator Model Router).
     */
    public function storeRouter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ip_address' => ['required', 'ip'],
            'api_port' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'api_username' => ['required', 'string', 'max:255'],
            'api_password' => ['required', 'string'],
            'ros_version' => ['required', Rule::in(['v6', 'v7'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $router = Router::create([
            'name' => $validated['name'],
            'ip_address' => $validated['ip_address'],
            'api_port' => $validated['api_port'] ?? 8728,
            'api_username' => $validated['api_username'],
            'api_password' => $validated['api_password'],
            'ros_version' => $validated['ros_version'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $router->id,
                'name' => $router->name,
                'ip_address' => $router->ip_address,
                'ros_version' => $router->ros_version,
                'is_active' => $router->is_active,
            ],
        ], 201);
    }

    /**
     * POST /api/admin/users
     * Membuat akun pelanggan/teknisi beserta daftar permissions & router_user.
     */
    public function storeUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'is_admin' => ['sometimes', 'boolean'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', Rule::in(User::AVAILABLE_PERMISSIONS)],
            'router_ids' => ['sometimes', 'array'],
            'router_ids.*' => ['integer', 'exists:routers,id'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_admin' => $validated['is_admin'] ?? false,
            'permissions' => array_values(array_unique($validated['permissions'])),
        ]);

        if (! empty($validated['router_ids'])) {
            $user->routers()->sync($validated['router_ids']);
        }

        return response()->json([
            'status' => 'success',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'permissions' => $user->permissions,
            ],
        ], 201);
    }

    /**
     * POST /api/admin/users/{id}/routers
     * Mengatur ulang daftar router yang boleh diakses oleh pelanggan tertentu.
     */
    public function syncUserRouters(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'router_ids' => ['required', 'array'],
            'router_ids.*' => ['integer', 'exists:routers,id'],
        ]);

        $user->routers()->sync($validated['router_ids']);

        return response()->json([
            'status' => 'success',
            'message' => "Akses router untuk {$user->name} berhasil diperbarui.",
        ]);
    }
}