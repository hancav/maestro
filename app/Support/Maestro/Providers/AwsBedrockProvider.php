<?php

namespace App\Support\Maestro\Providers;

use App\Support\Maestro\Contracts\LlmProvider;
use App\Support\Maestro\DTOs\ProviderResponse;
use App\Support\Maestro\Tools\ToolExecutor;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AwsBedrockProvider implements LlmProvider
{
    private int $maxToolIterations;

    /** @var callable|null */
    private $progressCallback = null;

    public function __construct(
        private readonly ToolExecutor $toolExecutor,
    ) {
        $this->maxToolIterations = (int) config('agents.providers.aws-bedrock.max_tool_iterations', 6);
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
        $region = config('agents.providers.aws-bedrock.region', 'us-east-1');
        $bearerToken = config('agents.providers.aws-bedrock.bearer_token');

        if (empty($bearerToken)) {
            throw new \InvalidArgumentException('AWS Bedrock bearer token not configured');
        }

        $this->emitProgress('A preparar pedido para o modelo...');

        $bedrockMessages = $this->prepareMessages($messages);
        $tools = ($config['skip_tools'] ?? false) ? [] : $this->toolExecutor->getAwsBedrockTools();
        $url = "https://bedrock-runtime.{$region}.amazonaws.com/model/{$model}/converse";

        $payload = [
            'messages' => $bedrockMessages,
            'system' => [
                ['text' => $systemPrompt],
            ],
            'inferenceConfig' => [
                'maxTokens' => (int) ($config['max_tokens'] ?? 4096),
                'temperature' => (float) ($config['temperature'] ?? 0.7),
            ],
        ];

        if (! empty($tools)) {
            $payload['toolConfig'] = ['tools' => $tools];
        }

        Log::info('AWS Bedrock request', [
            'model' => $model,
            'message_count' => count($bedrockMessages),
            'tools_count' => count($tools),
        ]);

        $this->emitProgress('A enviar pedido para o modelo...');

        try {
            $response = $this->makeRequest($url, $bearerToken, $payload);
            $data = $response->json();

            $this->emitProgress('Resposta recebida, a processar...');

            $inputTokens = $data['usage']['inputTokens'] ?? 0;
            $outputTokens = $data['usage']['outputTokens'] ?? 0;

            if (($data['stopReason'] ?? null) === 'tool_use') {
                return $this->handleToolUse($data, $bedrockMessages, $bearerToken, $url, $systemPrompt, $config);
            }

            $text = $this->extractTextFromResponse($data);

            if (empty(trim($text))) {
                $text = 'Não foi possível formular uma resposta. Por favor, tente reformular a sua pergunta.';
            }

            return ProviderResponse::create($text, $inputTokens, $outputTokens);
        } catch (\Exception $e) {
            Log::error('AWS Bedrock error', ['error' => $e->getMessage()]);

            throw new \RuntimeException('AWS Bedrock error: '.$e->getMessage(), 0, $e);
        }
    }

    public function getName(): string
    {
        return 'aws-bedrock';
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

    /** @return list<array{role: string, content: array}> */
    private function prepareMessages(array $messages): array
    {
        return collect($messages)
            ->filter(fn (array $msg): bool => $msg['role'] !== 'system')
            ->map(fn (array $msg): array => [
                'role' => $msg['role'],
                'content' => [['text' => $msg['content']]],
            ])
            ->values()
            ->toArray();
    }

    private function makeRequest(string $url, string $bearerToken, array $payload): Response
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$bearerToken,
            'Content-Type' => 'application/json',
        ])->timeout(120)->post($url, $payload);

        if (! $response->successful()) {
            Log::error('AWS Bedrock HTTP error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException($this->getFriendlyErrorMessage($response));
        }

        return $response;
    }

    private function getFriendlyErrorMessage(Response $response): string
    {
        $statusCode = $response->status();
        $errorData = json_decode($response->body(), true);
        $errorMessage = $errorData['message'] ?? $response->body();

        if (str_contains($errorMessage, 'capacity limit') || str_contains($errorMessage, 'try again later')) {
            return 'O serviço está temporariamente indisponível. Por favor, tente novamente.';
        }

        if (str_contains($errorMessage, 'throttl') || str_contains($errorMessage, 'rate limit')) {
            return 'Demasiados pedidos. Por favor, aguarde e tente novamente.';
        }

        return match (true) {
            $statusCode === 401, $statusCode === 403 => 'Erro de autenticação. Por favor, contacte o administrador.',
            $statusCode === 408, $statusCode === 504 => 'O pedido demorou muito tempo. Por favor, tente novamente.',
            $statusCode === 400 => 'Pedido inválido. Por favor, reformule a sua pergunta.',
            $statusCode >= 500 => 'Erro no serviço. Por favor, tente novamente.',
            default => 'Ocorreu um erro. Por favor, tente novamente.',
        };
    }

    private function extractTextFromResponse(array $data): string
    {
        foreach ($data['output']['message']['content'] ?? [] as $content) {
            if (isset($content['text'])) {
                $text = $content['text'];
                $text = preg_replace('/<thinking>.*?<\/thinking>/s', '', $text);
                $text = preg_replace('/<response>(.*?)<\/response>/s', '$1', $text);

                return trim($text);
            }
        }

        return '';
    }

    private function handleToolUse(
        array $response,
        array $messages,
        string $bearerToken,
        string $url,
        string $systemPrompt,
        array $config,
    ): ProviderResponse {
        $iteration = 0;
        $totalInputTokens = $response['usage']['inputTokens'] ?? 0;
        $totalOutputTokens = $response['usage']['outputTokens'] ?? 0;

        $this->emitProgress('Analisando pedido e preparando ferramentas...');

        while ($iteration < $this->maxToolIterations) {
            $iteration++;

            $toolUses = collect($response['output']['message']['content'] ?? [])
                ->filter(fn (array $content): bool => isset($content['toolUse']))
                ->toArray();

            foreach ($toolUses as $tool) {
                $toolName = $tool['toolUse']['name'] ?? '';
                $this->emitProgress("A executar ferramenta: {$toolName}...", [
                    'tool' => $toolName,
                    'type' => 'tool_start',
                ]);
            }

            $messages[] = [
                'role' => 'assistant',
                'content' => $this->normalizeToolUseContent($response['output']['message']['content']),
            ];

            $toolResults = $this->executeTools($toolUses);

            $messages[] = [
                'role' => 'user',
                'content' => $toolResults,
            ];

            $payload = [
                'messages' => $messages,
                'system' => [
                    ['text' => $systemPrompt],
                ],
                'inferenceConfig' => [
                    'maxTokens' => (int) ($config['max_tokens'] ?? 4096),
                    'temperature' => (float) ($config['temperature'] ?? 0.7),
                ],
                'toolConfig' => [
                    'tools' => $this->toolExecutor->getAwsBedrockTools(),
                ],
            ];

            $followUpResponse = $this->makeRequest($url, $bearerToken, $payload);
            $response = $followUpResponse->json();

            $totalInputTokens += $response['usage']['inputTokens'] ?? 0;
            $totalOutputTokens += $response['usage']['outputTokens'] ?? 0;

            if (($response['stopReason'] ?? null) === 'tool_use') {
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
            if (isset($item['toolUse'])) {
                if (empty($item['toolUse']['input']) || (is_array($item['toolUse']['input']) && array_keys($item['toolUse']['input']) === range(0, count($item['toolUse']['input']) - 1))) {
                    $item['toolUse']['input'] = new \stdClass;
                }
            }

            return $item;
        })->toArray();
    }

    /** @return list<array{toolResult: array}> */
    private function executeTools(array $toolUses): array
    {
        $toolResults = [];

        foreach ($toolUses as $toolUse) {
            $toolData = $toolUse['toolUse'];
            $toolName = $toolData['name'];
            $toolInput = $toolData['input'] ?? [];
            $toolUseId = $toolData['toolUseId'];

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
                    'toolResult' => [
                        'toolUseId' => $toolUseId,
                        'content' => [
                            ['json' => $result->toArray()],
                        ],
                    ],
                ];
            } catch (\Exception $e) {
                $this->emitProgress("Erro ao executar {$toolName}: {$e->getMessage()}", [
                    'tool' => $toolName,
                    'type' => 'tool_error',
                ]);

                $toolResults[] = [
                    'toolResult' => [
                        'toolUseId' => $toolUseId,
                        'content' => [
                            ['text' => 'Error: '.$e->getMessage()],
                        ],
                        'status' => 'error',
                    ],
                ];
            }
        }

        return $toolResults;
    }
}
