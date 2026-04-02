<?php

namespace App\Support\Maestro\DTOs;

class AgentConfig
{
    private const REQUIRED_FIELDS = [
        'name',
        'description',
        'provider',
        'model',
        'system_prompt',
    ];

    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly string $provider,
        public readonly string $model,
        public readonly string $systemPrompt,
        public readonly float $temperature = 0.7,
        public readonly int $maxTokens = 4096,
        public readonly array $tools = [],
        public readonly array $metadata = [],
    ) {}

    /**
     * @param  array{name: string, description: string, provider: string, model: string, system_prompt: string, temperature?: float, max_tokens?: int, tools?: array, metadata?: array}  $data
     *
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        $missing = array_filter(
            self::REQUIRED_FIELDS,
            fn (string $field): bool => ! array_key_exists($field, $data) || (is_string($data[$field]) && trim($data[$field]) === ''),
        );

        if ($missing !== []) {
            throw new \InvalidArgumentException(
                'Missing required fields: '.implode(', ', $missing),
            );
        }

        return new self(
            name: $data['name'],
            description: $data['description'],
            provider: $data['provider'],
            model: $data['model'],
            systemPrompt: $data['system_prompt'],
            temperature: (float) ($data['temperature'] ?? 0.7),
            maxTokens: (int) ($data['max_tokens'] ?? 4096),
            tools: $data['tools'] ?? [],
            metadata: $data['metadata'] ?? [],
        );
    }

    /**
     * @return array{name: string, description: string, provider: string, model: string, system_prompt: string, temperature: float, max_tokens: int, tools: array, metadata: array}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'provider' => $this->provider,
            'model' => $this->model,
            'system_prompt' => $this->systemPrompt,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
            'tools' => $this->tools,
            'metadata' => $this->metadata,
        ];
    }
}
