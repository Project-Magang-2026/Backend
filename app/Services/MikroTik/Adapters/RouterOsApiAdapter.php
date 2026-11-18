<?php

namespace App\Services\MikroTik\Adapters;

use App\Exceptions\MikroTikConnectionException;
use App\Models\Router;
use App\Services\MikroTik\Contracts\MikroTikAdapterInterface;
use Illuminate\Support\Facades\Crypt;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;
use Throwable;

class RouterOsApiAdapter implements MikroTikAdapterInterface
{
    private const BLOCK_COMMENT_PREFIX = 'ISP_BLOCK:';

    private function connect(Router $router): Client
    {
        try {
            $decryptedPassword = Crypt::decryptString($router->getRawOriginal('api_password'));

            $config = (new Config())
                ->set('host', $router->ip_address)
                ->set('user', $router->api_username)
                ->set('pass', $decryptedPassword)
                ->set('port', $router->api_port)
                ->set('timeout', (int) config('mikrotik.timeout', 5))
                ->set('attempts', (int) config('mikrotik.attempts', 1));

            return new Client($config);
        } catch (Throwable $e) {
            throw new MikroTikConnectionException(
                "Gagal terhubung ke router {$router->name} ({$router->ip_address}:{$router->api_port}): {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    public function getDevices(Router $router): array
    {
        $client = $this->connect($router);

        try {
            $blockedMacs = $this->fetchBlockedMacSet($client);
            $leases = $client->query('/ip/dhcp-server/lease/print')->read();
            $arpEntries = $client->query('/ip/arp/print')->read();

            $devicesMap = [];

            foreach ($leases as $lease) {
                $mac = strtoupper($lease['mac-address'] ?? '');
                if ($mac === '') {
                    continue;
                }

                $devicesMap[$mac] = [
                    'mac_address' => $mac,
                    'ip_address' => $lease['active-address'] ?? $lease['address'] ?? '',
                    'host_name' => $lease['host-name'] ?? 'unknown',
                    'is_blocked' => isset($blockedMacs[$mac]),
                ];
            }

            foreach ($arpEntries as $arp) {
                $mac = strtoupper($arp['mac-address'] ?? '');
                if ($mac === '' || isset($devicesMap[$mac])) {
                    continue;
                }

                $devicesMap[$mac] = [
                    'mac_address' => $mac,
                    'ip_address' => $arp['address'] ?? '',
                    'host_name' => $arp['comment'] ?? 'static-client',
                    'is_blocked' => isset($blockedMacs[$mac]),
                ];
            }

            return array_values($devicesMap);
        } catch (Throwable $e) {
            throw new MikroTikConnectionException("Gagal membaca daftar perangkat dari {$router->name}: {$e->getMessage()}", 0, $e);
        }
    }

    public function blockDevice(Router $router, string $macAddress, string $action): void
    {
        $client = $this->connect($router);
        $mac = strtoupper(str_replace('-', ':', $macAddress));
        $commentTag = self::BLOCK_COMMENT_PREFIX . $mac;

        try {
            $findQuery = (new Query('/ip/firewall/filter/print'))
                ->where('comment', $commentTag);
            $existingRules = $client->query($findQuery)->read();

            if ($action === 'block') {
                if (! empty($existingRules)) {
                    return;
                }

                $allRules = $client->query('/ip/firewall/filter/print')->read();

                $addQuery = (new Query('/ip/firewall/filter/add'))
                    ->equal('chain', 'forward')
                    ->equal('src-mac-address', $mac)
                    ->equal('action', 'drop')
                    ->equal('comment', $commentTag);

                if (! empty($allRules) && isset($allRules[0]['.id'])) {
                    $addQuery->equal('place-before', $allRules[0]['.id']);
                }

                $client->query($addQuery)->read();
                return;
            }

            foreach ($existingRules as $rule) {
                if (isset($rule['.id'])) {
                    $removeQuery = (new Query('/ip/firewall/filter/remove'))
                        ->equal('.id', $rule['.id']);
                    $client->query($removeQuery)->read();
                }
            }
        } catch (Throwable $e) {
            throw new MikroTikConnectionException("Gagal memproses {$action} MAC {$mac} pada {$router->name}: {$e->getMessage()}", 0, $e);
        }
    }

    public function updateWifi(Router $router, string $ssid, string $password): void
    {
        $client = $this->connect($router);

        try {
            if ($router->ros_version === 'v6') {
                $this->updateWifiRouterOsV6($client, $ssid, $password);
            } else {
                $this->updateWifiRouterOsV7($client, $ssid, $password);
            }
        } catch (Throwable $e) {
            throw new MikroTikConnectionException("Gagal memperbarui WiFi ({$router->ros_version}) pada {$router->name}: {$e->getMessage()}", 0, $e);
        }
    }

    public function getDiagnostics(Router $router): array
    {
        $client = $this->connect($router);

        try {
            $resource = $client->query('/system/resource/print')->read();
            $info = $resource[0] ?? [];

            return [
                'cpu_load' => (int) ($info['cpu-load'] ?? 0),
                'free_memory' => (int) ($info['free-memory'] ?? 0),
                'uptime' => (string) ($info['uptime'] ?? '0s'),
                'board_name' => (string) ($info['board-name'] ?? 'Unknown Board'),
                'version' => (string) ($info['version'] ?? $router->ros_version),
            ];
        } catch (Throwable $e) {
            throw new MikroTikConnectionException("Gagal mengambil diagnostik dari {$router->name}: {$e->getMessage()}", 0, $e);
        }
    }

    public function reboot(Router $router): void
    {
        $client = $this->connect($router);

        try {
            $client->query('/system/reboot')->read();
        } catch (Throwable $e) {
            if (! str_contains(strtolower($e->getMessage()), 'closed')) {
                throw new MikroTikConnectionException("Gagal mengirim perintah reboot ke {$router->name}: {$e->getMessage()}", 0, $e);
            }
        }
    }

    private function updateWifiRouterOsV6(Client $client, string $ssid, string $password): void
    {
        $interfaces = $client->query('/interface/wireless/print')->read();

        if (empty($interfaces)) {
            throw new MikroTikConnectionException('Tidak ditemukan interface /interface/wireless pada perangkat RouterOS v6.');
        }

        foreach ($interfaces as $iface) {
            if (($iface['master-interface'] ?? 'none') !== 'none') {
                continue;
            }

            $profileName = $iface['security-profile'] ?? 'default';

            $profileQuery = (new Query('/interface/wireless/security-profiles/print'))
                ->where('name', $profileName);
            $profiles = $client->query($profileQuery)->read();

            if (! empty($profiles[0]['.id'])) {
                $setProfile = (new Query('/interface/wireless/security-profiles/set'))
                    ->equal('.id', $profiles[0]['.id'])
                    ->equal('mode', 'dynamic-keys')
                    ->equal('authentication-types', 'wpa2-psk')
                    ->equal('wpa2-pre-shared-key', $password);
                $client->query($setProfile)->read();
            }

            $setIface = (new Query('/interface/wireless/set'))
                ->equal('.id', $iface['.id'])
                ->equal('ssid', $ssid);
            $client->query($setIface)->read();
        }
    }

    private function updateWifiRouterOsV7(Client $client, string $ssid, string $password): void
    {
        $interfaces = $client->query('/interface/wifi/print')->read();

        if (empty($interfaces)) {
            throw new MikroTikConnectionException('Tidak ditemukan interface /interface/wifi pada perangkat RouterOS v7.');
        }

        foreach ($interfaces as $iface) {
            if (isset($iface['master-interface'])) {
                continue;
            }

            $setWifi = (new Query('/interface/wifi/set'))
                ->equal('.id', $iface['.id'])
                ->equal('ssid', $ssid)
                ->equal('security.authentication-types', 'wpa2-psk')
                ->equal('security.passphrase', $password);

            $client->query($setWifi)->read();
        }
    }

    /**
     * @return array<string, true>
     */
    private function fetchBlockedMacSet(Client $client): array
    {
        $rules = $client->query('/ip/firewall/filter/print')->read();
        $blocked = [];

        foreach ($rules as $rule) {
            $comment = $rule['comment'] ?? '';
            if (str_starts_with($comment, self::BLOCK_COMMENT_PREFIX)) {
                $mac = strtoupper(substr($comment, strlen(self::BLOCK_COMMENT_PREFIX)));
                $blocked[$mac] = true;
            }
        }

        return $blocked;
    }
}