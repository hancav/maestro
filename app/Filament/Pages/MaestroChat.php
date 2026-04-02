<?php

namespace App\Filament\Pages;

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

            // TODO: Replace with actual Maestro orchestrator call
            // For now, return a placeholder response
            $responseText = "O Maestro ainda não está implementado. Esta é uma resposta placeholder.\n\nA tua mensagem foi: \"{$messageToSend}\"";
            $inputTokens = 0;
            $outputTokens = 0;

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

    public function getMaxContentWidth(): ?string
    {
        return 'full';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
