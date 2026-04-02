<?php

namespace App\Support\Maestro\Contracts;

use App\Support\Maestro\DTOs\ProviderResponse;

interface LlmProvider
{
    public function sendMessage(
        array $messages,
        string $systemPrompt,
        string $model,
        array $config = [],
    ): ProviderResponse;

    public function getName(): string;

    public function supportsTools(): bool;

    public function setProgressCallback(callable $callback): void;
}
