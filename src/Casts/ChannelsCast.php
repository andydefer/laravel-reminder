<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class ChannelsCast implements CastsAttributes
{
    /**
     * Convert JSON to array (always returns an array)
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return array
     */
    public function get($model, string $key, $value, array $attributes): array
    {
        // Handle null, empty string, or '[]' from database
        if (is_null($value) || $value === '[]' || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        // If decoding fails or doesn't return an array, return empty array
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * Convert array to JSON safely for storage
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return string
     */
    public function set($model, string $key, $value, array $attributes): string
    {
        // Handle null values
        if (is_null($value)) {
            return json_encode([]);
        }

        // Handle array values
        if (is_array($value)) {
            // Re-index array to avoid gaps and ensure valid JSON
            return json_encode(array_values($value));
        }

        // If value is neither null nor array, return empty array JSON
        return json_encode([]);
    }
}
