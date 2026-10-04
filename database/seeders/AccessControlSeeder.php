<?php

namespace Database\Seeders;

use App\Modules\Identity\Domain\Models\Permission;
use App\Modules\Identity\Domain\Models\Role;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            ['name' => 'Akses Admin', 'slug' => 'admin.access', 'group' => 'admin'],
            ['name' => 'Kelola Pengguna', 'slug' => 'admin.manage-users', 'group' => 'admin'],
            ['name' => 'Kelola Pengaturan', 'slug' => 'admin.manage-settings', 'group' => 'admin'],
            ['name' => 'Lihat Audit Log', 'slug' => 'admin.view-audit', 'group' => 'admin'],
            ['name' => 'Kelola Master Data', 'slug' => 'library.manage-master-data', 'group' => 'library'],
            ['name' => 'Kelola Ebook', 'slug' => 'library.manage-ebooks', 'group' => 'library'],
        ])->mapWithKeys(function (array $data) {
            $permission = Permission::query()->updateOrCreate(
                ['slug' => $data['slug']],
                $data,
            );

            return [$permission->slug => $permission];
        });

        $superAdmin = Role::query()->updateOrCreate(
            ['slug' => 'super-admin'],
            [
                'name' => 'Super Admin',
                'description' => 'Akses penuh ke seluruh area administrasi.',
                'is_system' => true,
            ],
        );

        $administrator = Role::query()->updateOrCreate(
            ['slug' => 'administrator'],
            [
                'name' => 'Administrator',
                'description' => 'Akses operasional area administrasi.',
                'is_system' => true,
            ],
        );

        $superAdmin->permissions()->sync($permissions->pluck('id')->all());
        $administrator->permissions()->sync(
            $permissions
                ->only([
                    'admin.access',
                    'admin.manage-settings',
                    'admin.view-audit',
                    'library.manage-master-data',
                    'library.manage-ebooks',
                ])
                ->pluck('id')
                ->all(),
        );
    }
}
