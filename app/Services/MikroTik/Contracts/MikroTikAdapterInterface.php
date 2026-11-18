<?php

namespace App\Services\MikroTik\Contracts;

use App\Models\Router;

interface MikroTikAdapterInterface
{
    /**
     * @return array<int, array{mac_address: string, ip_address: string, host_name: string, is_blocked: bool}>
     */
    public function getDevices(Router $router): array;

    public function blockDevice(Router $router, string $macAddress, string $action): void;

    public function updateWifi(Router $router, string $ssid, string $password): void;

    /**
     * @return array{cpu_load: int, free_memory: int, uptime: string, board_name: string, version: string}
     */
    public function getDiagnostics(Router $router): array;

    public function reboot(Router $router): void;
}