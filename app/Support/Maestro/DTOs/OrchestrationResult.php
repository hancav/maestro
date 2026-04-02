<?php

namespace App\Support\Maestro\DTOs;

class OrchestrationResult
{
    /**
     * @param  array<int, array{name: string, input: string, output: string, tokens: array, duration_ms: int}>  $agentHistory
     */
    public function __construct(
        public readonly string $response,
        public readonly bool $success,
        public readonly int $totalInputTokens = 0,
        public readonly int $totalOutputTokens = 0,
        public readonly array $agentHistory = [],
        public readonly ?string $error = null,
        public readonly array $metadata = [],
    ) {}

    public static function success(
        string $response,
        int $inputTokens,
        int $outputTokens,
        array $history,
        array $metadata = [],
    ): self {
        return new self(
            response: $response,
            success: true,
            totalInputTokens: $inputTokens,
            totalOutputTokens: $outputTokens,
            agentHistory: $history,
            metadata: $metadata,
        );
    }

    public static function failure(
        string $error,
        array $partialHistory = [],
        int $inputTokens = 0,
        int $outputTokens = 0,
    ): self {
        return new self(
            response: '',
            success: false,
            totalInputTokens: $inputTokens,
            totalOutputTokens: $outputTokens,
            agentHistory: $partialHistory,
            error: $error,
        );
    }

    /**
     * @return array{response: string, success: bool, total_input_tokens: int, total_output_tokens: int, agent_history: array, error: ?string, metadata: array}
     */
    public function toArray(): array
    {
        return [
            'response' => $this->response,
            'success' => $this->success,
            'total_input_tokens' => $this->totalInputTokens,
            'total_output_tokens' => $this->totalOutputTokens,
            'agent_history' => $this->agentHistory,
            'error' => $this->error,
            'metadata' => $this->metadata,
        ];
    }
}
