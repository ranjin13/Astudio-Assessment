<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Http\Middleware\CacheResponse;
use Illuminate\Support\Facades\Config;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        
        // Clear old API cache entries daily at midnight
        $schedule->call(function () {
            // Skip if caching is disabled
            if (!Config::get('api-cache.enabled', true)) {
                return;
            }
            
            $cacheKeys = Cache::get('api:cache_keys', []);
            $cacheKeysMeta = Cache::get('api:cache_keys_meta', []);
            $now = now();
            $cleared = 0;
            
            // Get max age from config
            $maxAgeDays = Config::get('api-cache.max_age_days', 3);
            
            foreach ($cacheKeysMeta as $key => $meta) {
                // Clear cache entries older than max age
                $createdAt = \Carbon\Carbon::parse($meta['created_at']);
                if ($now->diffInDays($createdAt) >= $maxAgeDays) {
                    Cache::forget($key);
                    
                    // Remove from tracking
                    if (($index = array_search($key, $cacheKeys)) !== false) {
                        unset($cacheKeys[$index]);
                    }
                    unset($cacheKeysMeta[$key]);
                    
                    $cleared++;
                }
            }
            
            // Update tracking
            Cache::put('api:cache_keys', $cacheKeys, now()->addDays(7));
            Cache::put('api:cache_keys_meta', $cacheKeysMeta, now()->addDays(7));
            
            Log::info('Cleared old API cache entries', [
                'count' => $cleared,
                'max_age_days' => $maxAgeDays
            ]);
        })->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
} 