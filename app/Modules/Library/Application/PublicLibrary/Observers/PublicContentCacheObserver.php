<?php

namespace App\Modules\Library\Application\PublicLibrary\Observers;

use App\Modules\Library\Application\PublicLibrary\PublicLibraryCache;
use Illuminate\Database\Eloquent\Model;

class PublicContentCacheObserver
{
    public function __construct(
        private readonly PublicLibraryCache $cache,
    ) {}

    public function saved(Model $model): void
    {
        $this->cache->flush();
    }

    public function deleted(Model $model): void
    {
        $this->cache->flush();
    }

    public function restored(Model $model): void
    {
        $this->cache->flush();
    }

    public function forceDeleted(Model $model): void
    {
        $this->cache->flush();
    }
}
