<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Crypt;

class Router extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'ip_address',
        'api_port',
        'api_username',
        'api_password',
        'ros_version',
        'is_active',
    ];

    /**
     * Kredensial API dilarang keras terekspos pada serialisasi JSON.
     */
    protected $hidden = [
        'api_username',
        'api_password',
    ];

    protected function casts(): array
    {
        return [
            'api_port' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Enkripsi otomatis setiap kali api_password diset/disimpan.
     * Dekripsi tidak ditaruh di accessor agar tidak pernah ter-decrypt secara tidak sengaja.
     */
    protected function apiPassword(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Crypt::encryptString($value),
        );
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'router_user');
    }
}