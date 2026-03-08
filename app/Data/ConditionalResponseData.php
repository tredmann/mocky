<?php

declare(strict_types=1);

namespace App\Data;

readonly class ConditionalResponseData
{
    public function __construct(
        public string $conditionSource,
        public string $conditionField,
        public string $conditionOperator,
        public string $conditionValue,
        public int $statusCode,
        public string $contentType,
        public ?string $responseBody,
        public int $priority,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            conditionSource: (string) $data['condition_source'],
            conditionField: (string) $data['condition_field'],
            conditionOperator: (string) $data['condition_operator'],
            conditionValue: (string) $data['condition_value'],
            statusCode: (int) $data['status_code'],
            contentType: (string) $data['content_type'],
            responseBody: isset($data['response_body']) ? (string) $data['response_body'] : null,
            priority: (int) ($data['priority'] ?? 0),
        );
    }
}
