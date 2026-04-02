<?php

namespace App\Support\Maestro\DTOs;

class ToolResult
{
    public function __construct(
        public readonly bool $success,
        public readonly mixed $data = null,
        public readonly ?string $error = null,
        public readonly array $metadata = [],
    ) {}

    public static function success(mixed $data, array $metadata = []): self
    {
        return new self(
            success: true,
            data: $data,
            metadata: $metadata,
        );
    }

    public static function failure(string $error, array $metadata = []): self
    {
        return new self(
            success: false,
            error: $error,
            metadata: $metadata,
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @return array{success: bool, data: mixed, error: ?string, metadata: array}
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'error' => $this->error,
            'metadata' => $this->metadata,
        ];
    }
}
