<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class ChannelsCast implements CastsAttributes
{
    /**
     * Convert JSON to array (toujours un tableau)
     */
    public function get($model, string $key, $value, array $attributes): array
    {
        if (is_null($value) || $value === '[]' || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * Convert array to JSON safely
     */
    public function set($model, string $key, $value, array $attributes): string
    {
        if (is_null($value)) {
            return json_encode([]);
        }

        if (is_array($value)) {
            // Réindexer le tableau pour éviter les trous
            return json_encode(array_values($value));
        }

        // Si ce n'est ni null ni array, on retourne un tableau vide
        return json_encode([]);
    }
}
