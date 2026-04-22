<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NodeBridgeAuth
{
    /**
     * Handle an incoming request.
     * Validates the API key from Node.js Bridge for security.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('x-api-key');
        $expectedKey = env('NODE_BRIDGE_SECRET_KEY', 'genify-node-bridge-secret-2026');

        if (!$apiKey || $apiKey !== $expectedKey) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid or missing API key. Provide x-api-key header.'
            ], 401);
        }

        return $next($request);
    }
}
