<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CacheMiddleware
{
    public function handle(Request $request, Closure $next, ?string $cacheTime = null)
    {
        // Skip caching for non-GET requests
        if (!$request->isMethod('get')) {
            return $next($request);
        }

        $cacheKey = $this->generateCacheKey($request);
        $ttl = $cacheTime ? (int)$cacheTime : 300; // Default 5 minutes

        return Cache::remember($cacheKey, $ttl, function () use ($request, $next) {
            $response = $next($request);
            
            // Only cache successful responses
            if ($response->getStatusCode() === 200) {
                return $response;
            }
            
            return $response;
        });
    }

    private function generateCacheKey(Request $request): string
    {
        $key = 'api_cache_' . md5($request->fullUrl() . serialize($request->all()));
        
        // Include user ID for user-specific cached content
        if ($request->user()) {
            $key .= '_user_' . $request->user()->id;
        }
        
        return $key;
    }
}