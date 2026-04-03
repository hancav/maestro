<?php

namespace App\Filament\Pages;

use App\Support\Maestro\Agent;
use App\Support\Maestro\AgentPool;
use App\Support\Maestro\Contracts\LlmProvider;
use App\Support\Maestro\DTOs\AgentConfig;
use App\Support\Maestro\Maestro;
use App\Support\Maestro\Providers\AnthropicProvider;
use App\Support\Maestro\Providers\AwsBedrockProvider;
use App\Support\Maestro\Providers\OllamaProvider;
use App\Support\Maestro\Tools\ToolExecutor;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;

/**
 * Maestro Chat Interface
 *
 * A Filament page that provides a chat interface for interacting
 * with the Maestro agent orchestrator. Adapted from the RTP Notícias
 * backoffice Assistant page.
 */
class MaestroChat extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Maestro';

    protected static ?string $slug = '/';

    protected static ?string $title = '';

    protected string $view = 'filament.pages.maestro-chat';

    protected static ?int $navigationSort = 1;

    protected static bool $shouldRegisterNavigation = true;

    // UI State
    public string $userMessage = '';

    public array $chatHistory = [];

    public bool $isProcessing = false;

    public array $progressUpdates = [];

    // Statistics
    public int $totalInputTokens = 0;

    public int $totalOutputTokens = 0;

    // Configuration
    public int $maxUiMessages = 40;

    private ?float $lastStepTime = null;

    public function mount(): void
    {
        //
    }

    /**
     * Send user message to the Maestro orchestrator
     */
    public function sendMessage(): void
    {
        if (empty($this->userMessage)) {
            return;
        }

        $messageToSend = $this->userMessage;
        $this->userMessage = '';

        // Add user message to chat history
        $userMessageData = [
            'role' => 'user',
            'content' => $messageToSend,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->chatHistory[] = $userMessageData;

        // Stream user message to UI immediately
        try {
            $this->stream(
                to: 'chat-messages-stream',
                content: view('components.chat-message', [
                    'message' => $userMessageData,
                    'index' => count($this->chatHistory) - 1,
                ])->render(),
                replace: false
            );
        } catch (\Exception $e) {
            Log::error('Failed to stream user message', ['error' => $e->getMessage()]);
        }

        // Reset progress
        $this->progressUpdates = [];
        $this->isProcessing = true;
        $this->lastStepTime = null;

        $startTime = microtime(true);

        try {
            $this->addProgressUpdate('A processar mensagem...');

            $maestro = $this->buildMaestro();

            $progressCallback = function (string $msg, array $meta = []): void {
                $this->addProgressUpdate($msg, $meta);
            };

            $result = $maestro->orchestrate(
                message: $messageToSend,
                history: $this->buildLlmHistory(),
                progressCallback: $progressCallback,
            );

            $responseText = $result->success
                ? $result->response
                : 'Erro na orquestração: '.($result->error ?? 'Erro desconhecido');
            $inputTokens = $result->totalInputTokens;
            $outputTokens = $result->totalOutputTokens;

            $totalTime = round((microtime(true) - $startTime) * 1000, 2);

            $assistantMessageData = [
                'role' => 'assistant',
                'content' => $responseText,
                'timestamp' => now()->toIso8601String(),
                'progress' => $this->progressUpdates,
                'total_time_ms' => $totalTime,
            ];

            $this->chatHistory[] = $assistantMessageData;

            // Stream assistant response
            try {
                $this->stream(
                    to: 'chat-messages-stream',
                    content: view('components.chat-message', [
                        'message' => $assistantMessageData,
                        'index' => count($this->chatHistory) - 1,
                    ])->render(),
                    replace: false
                );
            } catch (\Exception $e) {
                Log::error('Failed to stream assistant message', ['error' => $e->getMessage()]);
            }

            $this->totalInputTokens += $inputTokens;
            $this->totalOutputTokens += $outputTokens;
            $this->trimChatHistory();
        } catch (\Exception $e) {
            Log::error('Error in Maestro chat:', ['error' => $e->getMessage()]);

            $errorMessageData = [
                'role' => 'system',
                'content' => 'Erro: '.$e->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ];

            $this->chatHistory[] = $errorMessageData;

            try {
                $this->stream(
                    to: 'chat-messages-stream',
                    content: view('components.chat-message', [
                        'message' => $errorMessageData,
                        'index' => count($this->chatHistory) - 1,
                    ])->render(),
                    replace: false
                );
            } catch (\Exception $streamError) {
                Log::error('Failed to stream error message');
            }
        } finally {
            $this->isProcessing = false;
            $this->progressUpdates = [];
            $this->lastStepTime = null;
        }
    }

    /**
     * Add a progress update and stream to frontend
     */
    protected function addProgressUpdate(string $message, array $metadata = []): void
    {
        $currentTime = microtime(true);
        $stepDuration = $this->lastStepTime !== null
            ? round(($currentTime - $this->lastStepTime) * 1000, 2)
            : null;
        $this->lastStepTime = $currentTime;

        $this->progressUpdates[] = [
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
            'metadata' => $metadata,
            'step_duration_ms' => $stepDuration,
        ];

        try {
            $this->stream(
                to: 'realtime-progress',
                content: view('components.progress-item', [
                    'message' => $message,
                    'metadata' => $metadata,
                    'step_duration_ms' => $stepDuration,
                ])->render(),
                replace: false
            );

            $count = count($this->progressUpdates);
            $this->stream(
                to: 'progress-count',
                content: "({$count} ".($count === 1 ? 'passo' : 'passos').')',
                replace: true
            );
        } catch (\Exception $e) {
            Log::error('Failed to stream progress', ['error' => $e->getMessage()]);
        }
    }

    public function clearHistory(): void
    {
        $this->chatHistory = [];
        $this->totalInputTokens = 0;
        $this->totalOutputTokens = 0;
    }

    public function askQuestion(string $question): void
    {
        $this->userMessage = $question;
        $this->dispatch('question-selected');
    }

    protected function trimChatHistory(): void
    {
        if (count($this->chatHistory) > $this->maxUiMessages) {
            $this->chatHistory = array_slice($this->chatHistory, -$this->maxUiMessages);
        }
    }

    private function buildMaestro(): Maestro
    {
        $agentConfigs = config('agents.agents', []);
        $maestroConfig = config('agents.maestro', []);

        // Registry of available tools
        $availableTools = [
            'write_pdf_report' => new \App\Support\Maestro\Tools\PdfWriterTool,
        ];

        $providerFactory = function (AgentConfig $config) use ($availableTools): Agent {
            // Each agent gets its own ToolExecutor with only the tools it needs
            $toolExecutor = new ToolExecutor;
            foreach ($config->tools as $toolName) {
                if (isset($availableTools[$toolName])) {
                    $toolExecutor->register($availableTools[$toolName]);
                }
            }

            $provider = $this->createProvider($config->provider, $toolExecutor);

            return new Agent(
                config: $config,
                provider: $provider,
                toolExecutor: $toolExecutor,
            );
        };

        $pool = new AgentPool($agentConfigs, $providerFactory);

        $routerAgentConfig = AgentConfig::fromArray([
            'name' => 'maestro-router',
            'description' => 'Agente de routing do Maestro',
            'provider' => $maestroConfig['provider'] ?? 'aws-bedrock',
            'model' => $maestroConfig['model'] ?? 'us.amazon.nova-pro-v1:0',
            'system_prompt' => $maestroConfig['system_prompt'] ?? 'Tu és o Maestro.',
            'temperature' => $maestroConfig['temperature'] ?? 0.3,
            'max_tokens' => $maestroConfig['max_tokens'] ?? 1024,
        ]);

        $routerToolExecutor = new ToolExecutor;
        $routerProvider = $this->createProvider($routerAgentConfig->provider, $routerToolExecutor);

        $routerAgent = new Agent(
            config: $routerAgentConfig,
            provider: $routerProvider,
            toolExecutor: $routerToolExecutor,
        );

        return new Maestro(
            agentPool: $pool,
            routerAgent: $routerAgent,
            routerProvider: $routerProvider,
        );
    }

    private function createProvider(string $providerName, ToolExecutor $toolExecutor): LlmProvider
    {
        return match ($providerName) {
            'aws-bedrock' => new AwsBedrockProvider($toolExecutor),
            'anthropic' => new AnthropicProvider($toolExecutor),
            'ollama' => new OllamaProvider,
            default => throw new \InvalidArgumentException("Unsupported provider: {$providerName}"),
        };
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function buildLlmHistory(): array
    {
        return collect($this->chatHistory)
            ->filter(fn (array $msg): bool => in_array($msg['role'], ['user', 'assistant']))
            ->map(fn (array $msg): array => [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ])
            ->values()
            ->toArray();
    }

    public function getMaxContentWidth(): ?string
    {
        return 'full';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
