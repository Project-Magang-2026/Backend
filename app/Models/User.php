<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const AVAILABLE_PERMISSIONS = [
        'edit_wifi',
        'block_mac',
        'view_diagnostics',
        'full_control',
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'permissions',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public function routers(): BelongsToMany
    {
        return $this->belongsToMany(Router::class, 'router_user');
    }

    /**
     * Cek apakah user memiliki izin spesifik (full_control otomatis mencakup semua izin).
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];

        if (in_array('full_control', $permissions, true)) {
            return true;
        }

        return in_array($permission, $permissions, true);
    }

    /**
     * Cek khusus izin full_control tanpa fallback.
     */
    public function hasFullControl(): bool
    {
        return in_array('full_control', $this->permissions ?? [], true);
    }

    /**
     * Query router aktif yang boleh diakses oleh user ini.
     */
    public function accessibleRouters(): Builder
    {
        if ($this->hasFullControl()) {
            return Router::query()->where('is_active', true);
        }

        return $this->routers()
            ->where('routers.is_active', true)
            ->getQuery();
    }

    /**
     * Validasi otorisasi kepemilikan/akses terhadap 1 router spesifik.
     */
    public function canAccessRouter(Router $router): bool
    {
        if (! $router->is_active) {
            return false;
        }

        if ($this->hasFullControl()) {
            return true;
        }

        return $this->routers()
            ->where('routers.id', $router->id)
            ->exists();
    }
}