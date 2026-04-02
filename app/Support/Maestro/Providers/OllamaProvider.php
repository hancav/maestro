<?php

namespace App\Support\Maestro\Providers;

use App\Support\Maestro\Contracts\LlmProvider;
use App\Support\Maestro\DTOs\ProviderResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaProvider implements LlmProvider
{
    private string $baseUrl;

    private int $timeout;

    /** @var callable|null */
    private $progressCallback = null;

    public function __construct()
    {
        $this->baseUrl = (string) config('agents.providers.ollama.base_url', 'http://localhost:11434');
        $this->timeout = (int) config('agents.providers.ollama.timeout', 120);
    }

    public function setProgressCallback(callable $callback): void
    {
        $this->progressCallback = $callback;
    }

    public function sendMessage(
        array $messages,
        string $systemPrompt,
        string $model,
        array $config = [],
    ): ProviderResponse {
        $this->emitProgress('A preparar pedido para o modelo Ollama...');

        $ollamaMessages = $this->prepareMessages($messages, $systemPrompt);

        Log::info('Ollama request', [
            'url' => $this->baseUrl,
            'model' => $model,
            'message_count' => count($ollamaMessages),
        ]);

        $this->emitProgress('A enviar pedido para o modelo...');

        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/chat", [
                    'model' => $model,
                    'messages' => $ollamaMessages,
                    'stream' => false,
                ]);

            if (! $response->successful()) {
                Log::error('Ollama HTTP error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException('Ollama API error: '.$response->body());
            }

            $data = $response->json();
            $text = $data['message']['content'] ?? '';

            $this->emitProgress('Resposta recebida do modelo Ollama.');

            if (empty(trim($text))) {
                $text = 'Não foi possível formular uma resposta. Por favor, tente reformular a sua pergunta.';
            }

            return ProviderResponse::create($text, 0, 0);
        } catch (\Exception $e) {
            Log::error('Ollama error', ['error' => $e->getMessage()]);

            throw new \RuntimeException('Ollama error: '.$e->getMessage(), 0, $e);
        }
    }

    public function getName(): string
    {
        return 'ollama';
    }

    public function supportsTools(): bool
    {
        return false;
    }

    private function emitProgress(string $message, array $metadata = []): void
    {
        if ($this->progressCallback) {
            ($this->progressCallback)($message, $metadata);
        }
    }

    /** @return list<array{role: string, content: string}> */
    private function prepareMessages(array $messages, string $systemPrompt): array
    {
        return collect([
            ['role' => 'system', 'content' => $systemPrompt],
        ])
            ->concat(
                collect($messages)
                    ->filter(fn (array $msg): bool => $msg['role'] !== 'system')
                    ->map(fn (array $msg): array => [
                        'role' => $msg['role'],
                        'content' => $msg['content'],
                    ])
            )
            ->values()
            ->toArray();
    }
}
