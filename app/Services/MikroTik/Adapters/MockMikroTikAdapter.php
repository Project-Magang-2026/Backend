<?php

namespace App\Services\MikroTik\Adapters;

use App\Models\Router;
use App\Services\MikroTik\Contracts\MikroTikAdapterInterface;

class MockMikroTikAdapter implements MikroTikAdapterInterface
{
    public function getDevices(Router $router): array
    {
        return [
            [
                'mac_address' => 'AA:BB:CC:11:22:01',
                'ip_address' => '192.168.88.10',
                'host_name' => 'android-pixel-8',
                'is_blocked' => false,
            ],
            [
                'mac_address' => 'AA:BB:CC:11:22:02',
                'ip_address' => '192.168.88.15',
                'host_name' => 'macbook-pro-work',
                'is_blocked' => false,
            ],
            [
                'mac_address' => 'AA:BB:CC:11:22:03',
                'ip_address' => '192.168.88.24',
                'host_name' => 'smart-tv-livingroom',
                'is_blocked' => true,
            ],
            [
                'mac_address' => 'AA:BB:CC:11:22:04',
                'ip_address' => '192.168.88.31',
                'host_name' => 'unknown-device',
                'is_blocked' => false,
            ],
        ];
    }

    public function blockDevice(Router $router, string $macAddress, string $action): void
    {
        // Simulasi berhasil tanpa menyentuh router fisik
    }

    public function updateWifi(Router $router, string $ssid, string $password): void
    {
        // Simulasi berhasil untuk RouterOS v6 maupun v7
    }

    public function getDiagnostics(Router $router): array
    {
        $isV7 = $router->ros_version === 'v7';

        return [
            'cpu_load' => $isV7 ? 12 : 24,
            'free_memory' => $isV7 ? 198451200 : 41943040,
            'uptime' => '14d06h42m19s',
            'board_name' => $isV7 ? 'hAP ax2' : 'RB941-2nD (hAP lite)',
            'version' => $isV7 ? '7.16.1 (stable)' : '6.49.17 (long-term)',
        ];
    }

    public function reboot(Router $router): void
    {
        // Simulasi perintah /system/reboot
    }
}