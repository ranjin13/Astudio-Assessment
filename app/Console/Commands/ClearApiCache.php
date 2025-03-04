<?php

namespace App\Console\Commands;

use App\Http\Middleware\CacheResponse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearApiCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-api {--route= : Clear cache for a specific route}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear API response cache';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $route = $this->option('route');

        if ($route) {
            // Clear cache for a specific route pattern
            $keys = Cache::get('api:cache_keys', []);
            $cleared = 0;

            foreach ($keys as $key) {
                if (str_contains($key, $route)) {
                    Cache::forget($key);
                    $cleared++;
                }
            }

            $this->info("Cleared {$cleared} cache entries for route pattern: {$route}");
        } else {
            // Clear all API cache
            CacheResponse::clearAllCache();
            $this->info('All API cache cleared successfully');
        }

        return Command::SUCCESS;
    }
}
