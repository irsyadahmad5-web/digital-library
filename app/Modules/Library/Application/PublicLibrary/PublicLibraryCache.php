<?php

namespace App\Modules\Library\Application\PublicLibrary;

use Closure;
use Illuminate\Support\Facades\Cache;

class PublicLibraryCache
{
    private const VERSION_KEY = 'digital-library:public-cache-version';

    private const PREFIX = 'digital-library:public';

    public function remember(string $scope, int $seconds, Closure $callback): mixed
    {
        $seconds = max(1, min(3600, $seconds));
        $key = $this->key($scope);

        return Cache::remember(
            $key,
            now()->addSeconds($seconds),
            $callback,
        );
    }

    public function flush(): void
    {
        Cache::add(self::VERSION_KEY, 1, now()->addYears(5));
        Cache::increment(self::VERSION_KEY);
    }

    public function version(): int
    {
        return (int) Cache::rememberForever(
            self::VERSION_KEY,
            fn (): int => 1,
        );
    }

    public function key(string $scope): string
    {
        return self::PREFIX
            .':v'.$this->version()
            .':'.sha1($scope);
    }
}
