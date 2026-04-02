<?php

namespace App\Support\Maestro\DTOs;

class ProviderResponse
{
    public function __construct(
        public readonly string $text,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly array $metadata = [],
    ) {}

    public static function create(
        string $text,
        int $inputTokens = 0,
        int $outputTokens = 0,
        array $metadata = [],
    ): self {
        return new self($text, $inputTokens, $outputTokens, $metadata);
    }

    public function getTotalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    /**
     * @return array{text: string, input_tokens: int, output_tokens: int, total_tokens: int, metadata: array}
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'total_tokens' => $this->getTotalTokens(),
            'metadata' => $this->metadata,
        ];
    }
}
