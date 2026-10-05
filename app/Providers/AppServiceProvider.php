<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Library\Application\PublicLibrary\Observers\PublicContentCacheObserver;
use App\Modules\Library\Application\PublicLibrary\PublicLibraryCache;
use App\Modules\Library\Domain\Models\Author;
use App\Modules\Library\Domain\Models\Category;
use App\Modules\Library\Domain\Models\Collection;
use App\Modules\Library\Domain\Models\Ebook;
use App\Modules\Library\Domain\Models\EbookDownloadStat;
use App\Modules\Library\Domain\Models\EbookFile;
use App\Modules\Library\Domain\Models\Language;
use App\Modules\Library\Domain\Models\Publisher;
use App\Modules\Library\Domain\Models\Tag;
use App\Modules\Settings\Application\SettingsManager;
use App\Modules\Settings\Domain\Models\HomepageSection;
use App\Modules\Settings\Support\SettingsRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsRegistry::class);
        $this->app->singleton(PublicLibraryCache::class);
        $this->app->singleton(SettingsManager::class);
    }

    public function boot(): void
    {
        Gate::before(
            fn (User $user): ?bool => $user->hasRole('super-admin') ? true : null,
        );

        foreach ([
            Ebook::class,
            EbookFile::class,
            EbookDownloadStat::class,
            Author::class,
            Category::class,
            Publisher::class,
            Collection::class,
            Tag::class,
            Language::class,
            HomepageSection::class,
        ] as $model) {
            $model::observe(PublicContentCacheObserver::class);
        }

        foreach ([
            'admin.access',
            'admin.manage-users',
            'admin.manage-settings',
            'admin.view-audit',
            'library.manage-master-data',
            'library.manage-ebooks',
        ] as $permission) {
            Gate::define(
                $permission,
                fn (User $user): bool => $user->hasPermissionTo($permission),
            );
        }
    }
}
