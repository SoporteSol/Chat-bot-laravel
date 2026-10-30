<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    // No incluimos role_id acá para evitar asignación masiva de privilegios
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'solo_asignados'    => 'boolean',
        ];
    }

    // Rol asignado
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    // Instancia asignada si es usuario de empresa
    public function instancia(): BelongsTo
    {
        return $this->belongsTo(Instancia::class);
    }

    // El admin ve todo; el usuario de empresa solo su instancia
    public function canAccessInstancia(int $instanciaId): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->instancia_id !== null && (int) $this->instancia_id === $instanciaId;
    }

    // Verifica si tiene alguno de los roles indicados
    public function hasRole(string ...$roles): bool
    {
        if (!$this->role_id || !$this->role) {
            return false;
        }

        return in_array($this->role->name, $roles, true);
    }

    // Chequea si el rol tiene el permiso (el admin tiene acceso total)
    public function hasPermission(string $permissionName): bool
    {
        if (!$this->role) {
            return false;
        }

        if ($this->role->name === 'admin') {
            return true;
        }

        return $this->role->permissions->contains('name', $permissionName);
    }

    // Atajo para validar si es admin
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    // Claims extra para el token JWT
    public function getJWTCustomClaims(): array
    {
        return [
            'role'  => $this->role?->name ?? 'user',
            'name'  => $this->name,
            'email' => $this->email,
        ];
    }
}
