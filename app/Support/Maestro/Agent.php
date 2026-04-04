<?php

namespace App\Support\Maestro;

use App\Support\Maestro\Contracts\LlmProvider;
use App\Support\Maestro\DTOs\AgentConfig;
use App\Support\Maestro\DTOs\ProviderResponse;
use App\Support\Maestro\Tools\ToolExecutor;
use Illuminate\Support\Facades\Log;
use App\Support\Maestro\ContextManager;

class Agent
{
    public function __construct(
        private readonly AgentConfig $config,
        private readonly LlmProvider $provider,
        private readonly ToolExecutor $toolExecutor,
    ) {}

    public function handle(string $message, array $history = [], ?callable $progressCallback = null): ProviderResponse
    {
        $startTime = microtime(true);

        if ($progressCallback) {
            $this->provider->setProgressCallback($progressCallback);
        }

        $messages = $this->buildMessages($message, $history);

        $providerConfig = [
            'max_tokens' => $this->config->maxTokens,
            'temperature' => $this->config->temperature,
        ];

        if (empty($this->config->tools)) {
            $providerConfig['skip_tools'] = true;
        }

        try {
            if ($progressCallback) {
                $progressCallback("A executar agente '{$this->config->name}'...", [
                    'agent' => $this->config->name,
                    'type' => 'agent_start',
                ]);
            }

            Log::channel('maestro')->info("=== AGENT START: {$this->config->name} ===", [
                'agent' => $this->config->name,
                'model' => $this->config->model,
                'provider' => $this->config->provider,
                'temperature' => $this->config->temperature,
                'max_tokens' => $this->config->maxTokens,
                'tools' => $this->config->tools,
                'system_prompt_preview' => mb_substr($this->config->systemPrompt, 0, 200),
                'input_message' => mb_substr($message, 0, 500),
                'history_count' => count($history),
                'context_metadata' => ContextManager::getContextMetadata(),
            ]);

            // Inject dynamic temporal context into system prompt
            $contextualPrompt = ContextManager::buildContextualSystemPrompt($this->config->systemPrompt);

            $response = $this->provider->sendMessage(
                messages: $messages,
                systemPrompt: $contextualPrompt,
                model: $this->config->model,
                config: $providerConfig,
            );

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            if (empty(trim($response->text))) {
                Log::channel('maestro')->warning("=== AGENT EMPTY RESPONSE: {$this->config->name} ===", [
                    'agent' => $this->config->name,
                    'duration_ms' => $durationMs,
                    'input_tokens' => $response->inputTokens,
                    'output_tokens' => $response->outputTokens,
                ]);

                return ProviderResponse::create(
                    text: "O agente '{$this->config->name}' não conseguiu produzir uma resposta. Por favor, tente reformular a sua pergunta.",
                    inputTokens: $response->inputTokens,
                    outputTokens: $response->outputTokens,
                    metadata: array_merge($response->metadata, ['duration_ms' => $durationMs]),
                );
            }

            if ($progressCallback) {
                $progressCallback("Agente '{$this->config->name}' concluído.", [
                    'agent' => $this->config->name,
                    'type' => 'agent_complete',
                    'duration_ms' => $durationMs,
                ]);
            }

            Log::channel('maestro')->info("=== AGENT COMPLETE: {$this->config->name} ===", [
                'agent' => $this->config->name,
                'duration_ms' => $durationMs,
                'input_tokens' => $response->inputTokens,
                'output_tokens' => $response->outputTokens,
                'output_preview' => mb_substr($response->text, 0, 500),
            ]);

            return ProviderResponse::create(
                text: $response->text,
                inputTokens: $response->inputTokens,
                outputTokens: $response->outputTokens,
                metadata: array_merge($response->metadata, ['duration_ms' => $durationMs]),
            );
        } catch (\Exception $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::channel('maestro')->error('Agent execution failed', [
                'agent' => $this->config->name,
                'error' => $e->getMessage(),
                'duration_ms' => $durationMs,
            ]);

            throw $e;
        }
    }

    public function getConfig(): AgentConfig
    {
        return $this->config;
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function buildMessages(string $message, array $history): array
    {
        $messages = [];

        foreach ($history as $entry) {
            $messages[] = [
                'role' => $entry['role'],
                'content' => $entry['content'],
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $message,
        ];

        return $messages;
    }
}
