<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::before(
            fn (User $user): ?bool => $user->hasRole('super-admin') ? true : null,
        );

        foreach ([
            'admin.access',
            'admin.manage-users',
            'admin.manage-settings',
            'admin.view-audit',
        ] as $permission) {
            Gate::define(
                $permission,
                fn (User $user): bool => $user->hasPermissionTo($permission),
            );
        }
    }
}
