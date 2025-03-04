<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class CacheResponse
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if caching is disabled
        if (!Config::get('api-cache.enabled', true)) {
            return $next($request);
        }
        
        // Skip caching for POST, PUT, PATCH, DELETE methods
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        // Skip caching for excluded routes
        foreach (Config::get('api-cache.excluded_routes', []) as $route) {
            if (str_contains($request->path(), $route)) {
                return $next($request);
            }
        }

        // Generate cache key
        $cacheKey = $this->generateCacheKey($request);
        
        // Determine cache duration based on route
        $duration = $this->getCacheDuration($request);

        try {
            // Check if response is cached
            if (Cache::has($cacheKey)) {
                Log::info('Cache hit', [
                    'key' => $cacheKey,
                    'path' => $request->path(),
                    'method' => $request->method()
                ]);
                
                $cachedContent = Cache::get($cacheKey);
                $response = response($cachedContent['content']);
                
                // Restore headers from cached response
                foreach ($cachedContent['headers'] as $key => $value) {
                    $response->header($key, $value);
                }
                
                return $response;
            }

            // Get response from next middleware/controller
            $response = $next($request);

            // Only cache successful GET responses
            if ($request->method() === 'GET' && $response->getStatusCode() === 200) {
                // Store response content and headers
                $cachedContent = [
                    'content' => $response->getContent(),
                    'headers' => $response->headers->all()
                ];
                
                Cache::put($cacheKey, $cachedContent, now()->addMinutes($duration));
                
                // Track this cache key
                $this->trackCacheKey($cacheKey, $request->path());
                
                Log::info('Cached response', [
                    'key' => $cacheKey,
                    'path' => $request->path(),
                    'duration' => $duration,
                    'expires' => now()->addMinutes($duration)->toDateTimeString()
                ]);
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Cache error', [
                'key' => $cacheKey,
                'path' => $request->path(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $next($request);
        }
    }

    /**
     * Generate a unique cache key for the request
     */
    protected function generateCacheKey(Request $request): string
    {
        return 'api:' . md5(json_encode([
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_id' => $request->user()?->id ?? 'guest',
            'filters' => $request->get('filters'),
            'page' => $request->get('page'),
            'per_page' => $request->get('per_page'),
            'sort' => $request->get('sort'),
            'include' => $request->get('include')
        ]));
    }

    /**
     * Determine cache duration based on route
     */
    protected function getCacheDuration(Request $request): int
    {
        $path = $request->path();
        
        // Check for routes with longer cache duration
        $longCacheRoutes = Config::get('api-cache.long_cache_routes', []);
        foreach ($longCacheRoutes as $route => $duration) {
            if (str_contains($path, $route)) {
                return $duration;
            }
        }
        
        // Check for routes with shorter cache duration
        $shortCacheRoutes = Config::get('api-cache.short_cache_routes', []);
        foreach ($shortCacheRoutes as $route => $duration) {
            if (str_contains($path, $route)) {
                return $duration;
            }
        }
        
        // Return default duration
        return Config::get('api-cache.default_duration', 15);
    }

    /**
     * Track cache key for later management
     */
    protected function trackCacheKey(string $key, string $path): void
    {
        $cacheKeys = Cache::get('api:cache_keys', []);
        $cacheKeysMeta = Cache::get('api:cache_keys_meta', []);
        
        // Add key if not already tracked
        if (!in_array($key, $cacheKeys)) {
            $cacheKeys[] = $key;
            $cacheKeysMeta[$key] = [
                'path' => $path,
                'created_at' => now()->toDateTimeString()
            ];
            
            Cache::put('api:cache_keys', $cacheKeys, now()->addDays(7));
            Cache::put('api:cache_keys_meta', $cacheKeysMeta, now()->addDays(7));
        }
    }

    /**
     * Clear cache for a specific request
     */
    public static function clearCache(Request $request): void
    {
        // Skip if caching is disabled
        if (!Config::get('api-cache.enabled', true)) {
            return;
        }
        
        $instance = new static();
        $cacheKey = $instance->generateCacheKey($request);
        
        Cache::forget($cacheKey);
        
        // Remove from tracked keys
        $cacheKeys = Cache::get('api:cache_keys', []);
        $cacheKeysMeta = Cache::get('api:cache_keys_meta', []);
        
        if (($key = array_search($cacheKey, $cacheKeys)) !== false) {
            unset($cacheKeys[$key]);
            unset($cacheKeysMeta[$cacheKey]);
            
            Cache::put('api:cache_keys', $cacheKeys, now()->addDays(7));
            Cache::put('api:cache_keys_meta', $cacheKeysMeta, now()->addDays(7));
        }
        
        Log::info('Cleared cache', [
            'key' => $cacheKey,
            'path' => $request->path()
        ]);
    }

    /**
     * Clear all API cache
     */
    public static function clearAllCache(): void
    {
        // Skip if caching is disabled
        if (!Config::get('api-cache.enabled', true)) {
            return;
        }
        
        $cacheKeys = Cache::get('api:cache_keys', []);
        $count = count($cacheKeys);
        
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
        
        Cache::forget('api:cache_keys');
        Cache::forget('api:cache_keys_meta');
        
        Log::info('Cleared all API cache', [
            'count' => $count
        ]);
    }
}
