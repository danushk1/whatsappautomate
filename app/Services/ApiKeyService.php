<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class ApiKeyService
{
    /**
     * Generate a unique, cryptographically secure API key.
     *
     * @param string $prefix An optional prefix for the key (e.g., 'aib_')
     * @param int $length The length of the random string (excluding prefix)
     * @return string
     */
    public function generateUniqueApiKey(string $prefix = 'aib_', int $length = 40): string
    {
        do {
            // Str::random uses random_bytes() under the hood, making it cryptographically secure
            $apiKey = $prefix . Str::random($length);
            
            // Check against the database to ensure there's absolutely no collision
            $keyExists = User::where('api_key', $apiKey)->exists();
            
        } while ($keyExists);

        return $apiKey;
    }
}
