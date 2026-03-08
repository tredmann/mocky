<?php

declare(strict_types=1);

namespace App\Services;

class ResponseBodyFormatter
{
    public function format(string $contentType, ?string $body): ?string
    {
        if ($body === null || $body === '') {
            return $body;
        }

        if (str_contains($contentType, 'json')) {
            $decoded = json_decode($body);
            if (json_last_error() === JSON_ERROR_NONE) {
                return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        return $body;
    }
}
