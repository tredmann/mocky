<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidResponseSyntax implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    protected array $data = [];

    private string $contentTypeField;

    public function __construct(string $contentTypeField = 'content_type')
    {
        $this->contentTypeField = $contentTypeField;
    }

    /** @param array<string, mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $contentType = $this->data[$this->contentTypeField] ?? '';

        if (str_contains($contentType, 'json')) {
            json_decode($value);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $fail('The :attribute contains invalid JSON: '.json_last_error_msg());
            }
        } elseif (str_contains($contentType, 'xml')) {
            $previous = libxml_use_internal_errors(true);
            simplexml_load_string($value);
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            if (! empty($errors)) {
                $fail('The :attribute contains invalid XML: '.$errors[0]->message);
            }
        }
    }
}
