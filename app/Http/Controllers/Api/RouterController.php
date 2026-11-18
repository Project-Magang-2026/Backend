<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\MikroTikConnectionException;
use App\Http\Controllers\Controller;
use App\Models\Router;
use App\Models\User;
use App\Services\MikroTik\MikroTikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RouterController extends Controller
{
    public function __construct(
        private readonly MikroTikService $mikroTikService
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $routers = $user->accessibleRouters()
            ->select(['routers.id', 'routers.name', 'routers.ip_address', 'routers.ros_version'])
            ->orderBy('routers.id')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $routers,
        ]);
    }

    public function devices(Request $request, int $id): JsonResponse
    {
        $router = $this->resolveAuthorizedRouter($request->user(), $id);
        if ($router instanceof JsonResponse) {
            return $router;
        }

        try {
            $devices = $this->mikroTikService->getDevices($router);

            return response()->json([
                'status' => 'success',
                'data' => $devices,
            ]);
        } catch (MikroTikConnectionException $e) {
            return $this->routerErrorResponse($e);
        }
    }

    public function blockDevice(Request $request, int $id): JsonResponse
    {
        $router = $this->resolveAuthorizedRouter($request->user(), $id, 'block_mac');
        if ($router instanceof JsonResponse) {
            return $router;
        }

        $validated = $request->validate([
            'mac_address' => ['required', 'string', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/'],
            'action' => ['required', 'string', Rule::in(['block', 'unblock'])],
        ]);

        try {
            $this->mikroTikService->blockDevice(
                $router,
                $validated['mac_address'],
                $validated['action']
            );

            $verb = $validated['action'] === 'block' ? 'diblokir' : 'dibuka blokirnya';

            return response()->json([
                'status' => 'success',
                'message' => "Perangkat {$validated['mac_address']} berhasil {$verb} pada {$router->name}.",
            ]);
        } catch (MikroTikConnectionException $e) {
            return $this->routerErrorResponse($e);
        }
    }

    public function updateWifi(Request $request, int $id): JsonResponse
    {
        $router = $this->resolveAuthorizedRouter($request->user(), $id, 'edit_wifi');
        if ($router instanceof JsonResponse) {
            return $router;
        }

        $validated = $request->validate([
            'ssid' => ['required', 'string', 'min:1', 'max:32'],
            'password' => ['required', 'string', 'min:8', 'max:63'],
        ]);

        try {
            $this->mikroTikService->updateWifi(
                $router,
                $validated['ssid'],
                $validated['password']
            );

            return response()->json([
                'status' => 'success',
                'message' => "Konfigurasi WiFi (SSID: {$validated['ssid']}) berhasil diperbarui pada {$router->name}.",
            ]);
        } catch (MikroTikConnectionException $e) {
            return $this->routerErrorResponse($e);
        }
    }

    public function diagnostics(Request $request, int $id): JsonResponse
    {
        $router = $this->resolveAuthorizedRouter($request->user(), $id, 'view_diagnostics');
        if ($router instanceof JsonResponse) {
            return $router;
        }

        try {
            $diagnostics = $this->mikroTikService->getDiagnostics($router);

            return response()->json([
                'status' => 'success',
                'data' => $diagnostics,
            ]);
        } catch (MikroTikConnectionException $e) {
            return $this->routerErrorResponse($e);
        }
    }

    public function reboot(Request $request, int $id): JsonResponse
    {
        $router = $this->resolveAuthorizedRouter($request->user(), $id, 'full_control');
        if ($router instanceof JsonResponse) {
            return $router;
        }

        try {
            $this->mikroTikService->reboot($router);

            return response()->json([
                'status' => 'success',
                'message' => "Perintah reboot berhasil dikirim ke {$router->name}.",
            ]);
        } catch (MikroTikConnectionException $e) {
            return $this->routerErrorResponse($e);
        }
    }

    private function resolveAuthorizedRouter(User $user, int $routerId, ?string $requiredPermission = null): Router|JsonResponse
    {
        $router = Router::where('id', $routerId)
            ->where('is_active', true)
            ->first();

        if (! $router) {
            return response()->json([
                'status' => 'error',
                'message' => 'Router tidak ditemukan atau sedang non-aktif.',
            ], 404);
        }

        if (! $user->canAccessRouter($router)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki akses ke router ini.',
            ], 403);
        }

        if ($requiredPermission !== null && ! $user->hasPermission($requiredPermission)) {
            return response()->json([
                'status' => 'error',
                'message' => "Izin '{$requiredPermission}' diperlukan untuk melakukan aksi ini.",
            ], 403);
        }

        return $router;
    }

    private function routerErrorResponse(MikroTikConnectionException $e): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 502);
    }
}