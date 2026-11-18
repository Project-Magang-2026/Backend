<?php

namespace Database\Seeders;

use App\Models\Router;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat Sample Routers (password otomatis terenkripsi oleh mutator Router::apiPassword)
        $routerCustA = Router::create([
            'name' => 'CPE-Banten-01 (Cluster Serang)',
            'ip_address' => '10.20.10.2',
            'api_port' => 8728,
            'api_username' => 'isp_api_svc',
            'api_password' => 'R0uterS3cr3t#2026',
            'ros_version' => 'v7',
            'is_active' => true,
        ]);

        $routerCustB = Router::create([
            'name' => 'CPE-Banten-02 (Branch Office)',
            'ip_address' => '10.20.10.3',
            'api_port' => 8728,
            'api_username' => 'isp_api_svc',
            'api_password' => 'R0uterS3cr3t#2026',
            'ros_version' => 'v6',
            'is_active' => true,
        ]);

        $routerCore = Router::create([
            'name' => 'DIST-POP-Cilegon-01',
            'ip_address' => '10.20.1.1',
            'api_port' => 8728,
            'api_username' => 'isp_noc_admin',
            'api_password' => 'NocM@sterK3y!99',
            'ros_version' => 'v7',
            'is_active' => true,
        ]);

        // Router non-aktif untuk memverifikasi filter is_active
        Router::create([
            'name' => 'CPE-Decommissioned-99',
            'ip_address' => '10.20.99.99',
            'api_port' => 8728,
            'api_username' => 'isp_api_svc',
            'api_password' => 'InactivePass123',
            'ros_version' => 'v6',
            'is_active' => false,
        ]);

        // 2. Akun Super Admin
        User::create([
            'name' => 'Super Admin ISP',
            'email' => 'admin@isp.local',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'permissions' => [
                'edit_wifi',
                'block_mac',
                'view_diagnostics',
                'full_control',
            ],
        ]);

        // 3. Akun Pelanggan Contoh (hanya terhubung ke router milik pelanggan via pivot router_user)
        $pelanggan = User::create([
            'name' => 'Pelanggan Contoh',
            'email' => 'pelanggan@isp.local',
            'password' => Hash::make('password'),
            'is_admin' => false,
            'permissions' => [
                'edit_wifi',
                'block_mac',
            ],
        ]);
        $pelanggan->routers()->attach([$routerCustA->id, $routerCustB->id]);

        // 4. Akun Teknisi Contoh (akses otomatis ke seluruh router aktif via full_control)
        User::create([
            'name' => 'Teknisi Lapangan NOC',
            'email' => 'teknisi@isp.local',
            'password' => Hash::make('password'),
            'is_admin' => false,
            'permissions' => [
                'edit_wifi',
                'block_mac',
                'view_diagnostics',
                'full_control',
            ],
        ]);
    }
}