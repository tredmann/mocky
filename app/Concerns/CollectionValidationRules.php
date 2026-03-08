<?php

namespace App\Concerns;

trait CollectionValidationRules
{
    /**
     * Get the validation rules for a collection.
     *
     * @return array<string, array<int, string>>
     */
    protected function collectionRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
