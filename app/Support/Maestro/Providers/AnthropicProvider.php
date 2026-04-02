<?php

namespace App\Support\Maestro\Providers;

use App\Support\Maestro\Contracts\LlmProvider;
use App\Support\Maestro\DTOs\ProviderResponse;
use App\Support\Maestro\Tools\ToolExecutor;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicProvider implements LlmProvider
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    private int $maxToolIterations;

    private string $apiVersion;

    /** @var callable|null */
    private $progressCallback = null;

    public function __construct(
        private readonly ToolExecutor $toolExecutor,
    ) {
        $this->maxToolIterations = (int) config('agents.providers.anthropic.max_tool_iterations', 10);
        $this->apiVersion = (string) config('agents.providers.anthropic.api_version', '2023-06-01');
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
        $apiKey = config('agents.providers.anthropic.api_key');

        if (empty($apiKey)) {
            throw new \InvalidArgumentException('Anthropic API key not configured');
        }

        $this->emitProgress('A preparar pedido para o modelo...');

        $anthropicMessages = $this->prepareMessages($messages);
        $tools = ($config['skip_tools'] ?? false) ? [] : $this->toolExecutor->getAvailableTools();

        $payload = [
            'model' => $model,
            'max_tokens' => (int) ($config['max_tokens'] ?? 4096),
            'system' => $systemPrompt,
            'messages' => $anthropicMessages,
        ];

        if (! empty($tools)) {
            $payload['tools'] = $tools;
        }

        Log::info('Anthropic request', [
            'model' => $model,
            'message_count' => count($anthropicMessages),
            'tools_count' => count($tools),
        ]);

        $this->emitProgress('A enviar pedido para o modelo...');

        try {
            $response = $this->makeRequest($apiKey, $payload);
            $data = $response->json();

            $this->emitProgress('Resposta recebida, a processar...');

            $inputTokens = $data['usage']['input_tokens'] ?? 0;
            $outputTokens = $data['usage']['output_tokens'] ?? 0;

            if (($data['stop_reason'] ?? null) === 'tool_use') {
                return $this->handleToolUse($data, $anthropicMessages, $apiKey, $model, $systemPrompt, $config);
            }

            $text = $this->extractTextFromResponse($data);

            if (empty(trim($text))) {
                $text = 'Não foi possível formular uma resposta. Por favor, tente reformular a sua pergunta.';
            }

            return ProviderResponse::create($text, $inputTokens, $outputTokens);
        } catch (\Exception $e) {
            Log::error('Anthropic error', ['error' => $e->getMessage()]);

            throw new \RuntimeException('Anthropic error: '.$e->getMessage(), 0, $e);
        }
    }

    public function getName(): string
    {
        return 'anthropic';
    }

    public function supportsTools(): bool
    {
        return true;
    }

    private function emitProgress(string $message, array $metadata = []): void
    {
        if ($this->progressCallback) {
            ($this->progressCallback)($message, $metadata);
        }
    }

    /** @return list<array{role: string, content: string}> */
    private function prepareMessages(array $messages): array
    {
        return collect($messages)
            ->filter(fn (array $msg): bool => $msg['role'] !== 'system')
            ->map(fn (array $msg): array => [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ])
            ->values()
            ->toArray();
    }

    private function makeRequest(string $apiKey, array $payload): Response
    {
        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => $this->apiVersion,
            'content-type' => 'application/json',
        ])->timeout(120)->post(self::API_URL, $payload);

        if (! $response->successful()) {
            Log::error('Anthropic HTTP error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Anthropic API error: '.$response->body());
        }

        return $response;
    }

    private function extractTextFromResponse(array $data): string
    {
        foreach ($data['content'] ?? [] as $content) {
            if ($content['type'] === 'text') {
                return trim($content['text']);
            }
        }

        return '';
    }

    private function handleToolUse(
        array $response,
        array $messages,
        string $apiKey,
        string $model,
        string $systemPrompt,
        array $config,
    ): ProviderResponse {
        $iteration = 0;
        $totalInputTokens = $response['usage']['input_tokens'] ?? 0;
        $totalOutputTokens = $response['usage']['output_tokens'] ?? 0;

        $this->emitProgress('Analisando pedido e preparando ferramentas...');

        while ($iteration < $this->maxToolIterations) {
            $iteration++;

            $toolUses = collect($response['content'])
                ->filter(fn (array $content): bool => $content['type'] === 'tool_use')
                ->toArray();

            foreach ($toolUses as $tool) {
                $toolName = $tool['name'] ?? '';
                $this->emitProgress("A executar ferramenta: {$toolName}...", [
                    'tool' => $toolName,
                    'type' => 'tool_start',
                ]);
            }

            $messages[] = [
                'role' => 'assistant',
                'content' => $this->normalizeToolUseContent($response['content']),
            ];

            $toolResults = $this->executeTools($toolUses);

            $messages[] = [
                'role' => 'user',
                'content' => $toolResults,
            ];

            $followUpResponse = $this->makeRequest($apiKey, [
                'model' => $model,
                'max_tokens' => (int) ($config['max_tokens'] ?? 4096),
                'system' => $systemPrompt,
                'messages' => $messages,
                'tools' => $this->toolExecutor->getAvailableTools(),
            ]);

            $response = $followUpResponse->json();

            $totalInputTokens += $response['usage']['input_tokens'] ?? 0;
            $totalOutputTokens += $response['usage']['output_tokens'] ?? 0;

            if (($response['stop_reason'] ?? null) === 'tool_use') {
                $this->emitProgress('O modelo precisa de mais informação, a executar ferramentas adicionais...');

                continue;
            }

            $this->emitProgress('A formular resposta final...');
            $text = $this->extractTextFromResponse($response);

            if (empty(trim($text))) {
                $text = 'Não foi possível formular uma resposta com base nos dados obtidos. Por favor, tente reformular a sua pergunta.';
            }

            return ProviderResponse::create($text, $totalInputTokens, $totalOutputTokens);
        }

        return ProviderResponse::create(
            'Não foi possível processar completamente a sua pergunta. Por favor, reformule de forma mais específica.',
            $totalInputTokens,
            $totalOutputTokens,
        );
    }

    private function normalizeToolUseContent(array $content): array
    {
        return collect($content)->map(function (array $item): array {
            if ($item['type'] === 'tool_use') {
                if (empty($item['input']) || (is_array($item['input']) && array_keys($item['input']) === range(0, count($item['input']) - 1))) {
                    $item['input'] = new \stdClass;
                }
            }

            return $item;
        })->toArray();
    }

    /** @return list<array{type: string, tool_use_id: string, content: string, is_error?: bool}> */
    private function executeTools(array $toolUses): array
    {
        $toolResults = [];

        foreach ($toolUses as $toolUse) {
            $toolName = $toolUse['name'];
            $toolInput = $toolUse['input'] ?? [];
            $toolUseId = $toolUse['id'];

            if (empty($toolInput) || (is_array($toolInput) && array_keys($toolInput) === range(0, count($toolInput) - 1))) {
                $toolInput = [];
            }

            $this->emitProgress("A executar {$toolName}...", [
                'tool' => $toolName,
                'type' => 'tool_executing',
            ]);

            try {
                $result = $this->toolExecutor->execute($toolName, $toolInput);

                $this->emitProgress("Ferramenta {$toolName} executada", [
                    'tool' => $toolName,
                    'type' => 'tool_complete',
                ]);

                $toolResults[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $toolUseId,
                    'content' => json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                ];
            } catch (\Exception $e) {
                $this->emitProgress("Erro ao executar {$toolName}: {$e->getMessage()}", [
                    'tool' => $toolName,
                    'type' => 'tool_error',
                ]);

                $toolResults[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $toolUseId,
                    'content' => 'Error: '.$e->getMessage(),
                    'is_error' => true,
                ];
            }
        }

        return $toolResults;
    }
}
