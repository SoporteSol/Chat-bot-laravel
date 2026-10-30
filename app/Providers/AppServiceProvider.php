<?php

namespace App\Providers;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        try {
            // Registramos en Gates los permisos guardados en bd
            if (Schema::hasTable('permissions')) {
                $permissions = Permission::with('roles')->get();

                foreach ($permissions as $permission) {
                    Gate::define($permission->name, function (User $user) use ($permission) {
                        return $user->hasPermission($permission->name);
                    });
                }
            }
        } catch (\Throwable $e) {
            // Por si todavía no corrieron las migraciones iniciales
            report($e);
        }
    }
}
