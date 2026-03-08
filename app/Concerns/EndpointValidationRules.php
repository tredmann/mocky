<?php

namespace App\Concerns;

use App\Rules\ValidResponseSyntax;
use Illuminate\Validation\Rule;

trait EndpointValidationRules
{
    public const SLUG_REGEX = '/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?$/';

    /**
     * Get the validation rules for an endpoint.
     *
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>>
     */
    protected function endpointRules(string $collectionId, string $method, ?string $ignoreId = null): array
    {
        $slugUnique = Rule::unique('endpoints', 'slug')
            ->where(fn ($query) => $query->where('collection_id', $collectionId)->where('method', $method));

        if ($ignoreId !== null) {
            $slugUnique = $slugUnique->ignore($ignoreId);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:'.self::SLUG_REGEX, $slugUnique],
            'description' => ['nullable', 'string', 'max:1000'],
            'method' => ['required', 'in:GET,POST,PUT,PATCH,DELETE'],
            'status_code' => ['required', 'integer', 'min:100', 'max:599'],
            'content_type' => ['required', 'string', 'max:255'],
            'response_body' => ['nullable', 'string', new ValidResponseSyntax],
        ];
    }
}
