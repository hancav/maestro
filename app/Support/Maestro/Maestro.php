<?php

namespace App\Support\Maestro;

use App\Support\Maestro\Contracts\LlmProvider;
use App\Support\Maestro\DTOs\AgentConfig;
use App\Support\Maestro\DTOs\OrchestrationResult;
use App\Support\Maestro\Tools\ToolExecutor;
use Illuminate\Support\Facades\Log;

class Maestro
{
    public function __construct(
        private readonly AgentPool $agentPool,
        private readonly Agent $routerAgent,
        private readonly ?LlmProvider $routerProvider = null,
    ) {}

    public function orchestrate(
        string $message,
        array $history = [],
        ?callable $progressCallback = null,
    ): OrchestrationResult {
        $startTime = microtime(true);

        Log::channel('maestro')->info('Maestro: tarefa recebida', [
            'message_length' => strlen($message),
            'history_count' => count($history),
        ]);

        if ($progressCallback) {
            $progressCallback('Maestro: a analisar tarefa e decidir routing...', [
                'type' => 'routing_start',
            ]);
        }

        try {
            $routingDecision = $this->resolveRouting($message, $history, $progressCallback);
        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::channel('maestro')->error('Maestro: routing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => $duration,
            ]);

            return OrchestrationResult::failure(
                error: 'Não foi possível determinar como processar a tarefa: '.$e->getMessage(),
            );
        }

        $agents = $routingDecision['agents'] ?? [];
        $agentNames = array_column($agents, 'name');

        Log::channel('maestro')->info('=== MAESTRO ROUTING DECISION ===', [
            'agents' => $agents,
            'original_message' => mb_substr($message, 0, 500),
        ]);

        if (empty($agents)) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::channel('maestro')->warning('Maestro: nenhum agente selecionado', [
                'duration_ms' => $duration,
            ]);

            return OrchestrationResult::success(
                response: 'Não foi possível determinar qual agente utilizar para esta tarefa. '
                    .'Por favor, reformule a sua pergunta ou especifique o tipo de tarefa desejada.',
                inputTokens: 0,
                outputTokens: 0,
                history: [],
                metadata: ['total_duration_ms' => $duration, 'routing' => 'none'],
            );
        }

        if ($progressCallback) {
            $agentList = implode(' → ', $agentNames);
            $progressCallback("Maestro: routing decidido — {$agentList}", [
                'type' => 'routing_complete',
                'agents' => $agentNames,
            ]);
        }

        if (count($agents) === 1) {
            $firstAgent = $agents[0];
            $agentMessage = ! empty($firstAgent['instruction']) ? $firstAgent['instruction'] : $message;

            return $this->executeSingleAgent($firstAgent['name'], $agentMessage, $history, $progressCallback, $startTime);
        }

        return $this->executePipeline($agents, $message, $progressCallback, $startTime);
    }

    /**
     * @return array{agents: list<string>, message?: string}
     */
    private function resolveRouting(string $message, array $history, ?callable $progressCallback): array
    {
        $dynamicSystemPrompt = $this->buildRouterSystemPrompt();

        $originalConfig = $this->routerAgent->getConfig();
        $routerConfig = new AgentConfig(
            name: $originalConfig->name,
            description: $originalConfig->description,
            provider: $originalConfig->provider,
            model: $originalConfig->model,
            systemPrompt: $dynamicSystemPrompt,
            temperature: $originalConfig->temperature,
            maxTokens: $originalConfig->maxTokens,
            tools: [],
            metadata: $originalConfig->metadata,
        );

        $provider = $this->routerProvider ?? $this->extractProvider($this->routerAgent);

        $routerWithDynamicPrompt = new Agent(
            config: $routerConfig,
            provider: $provider,
            toolExecutor: new ToolExecutor,
        );

        $response = $routerWithDynamicPrompt->handle($message, $history, $progressCallback);

        Log::channel('maestro')->info('=== MAESTRO ROUTING RAW RESPONSE ===', [
            'raw_response' => $response->text,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
        ]);

        $json = $this->parseRoutingJson($response->text);

        if ($json === null) {
            Log::channel('maestro')->warning('Maestro: falha ao interpretar routing JSON', [
                'raw_response' => $response->text,
            ]);

            return ['agents' => []];
        }

        return $json;
    }

    private function extractProvider(Agent $agent): LlmProvider
    {
        $reflection = new \ReflectionClass($agent);
        $prop = $reflection->getProperty('provider');

        return $prop->getValue($agent);
    }

    /**
     * @return array{agents: list<array{name: string, instruction: string}>}|null
     */
    private function parseRoutingJson(string $text): ?array
    {
        $text = trim($text);

        // Extract JSON from response
        if (preg_match('/\{.*"agents"\s*:\s*\[.*\].*\}/s', $text, $matches)) {
            $text = $matches[0];
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded) || ! isset($decoded['agents']) || ! is_array($decoded['agents'])) {
            return null;
        }

        $agents = [];
        foreach ($decoded['agents'] as $agent) {
            if (is_string($agent)) {
                // Legacy format: just agent name string
                $agents[] = ['name' => $agent, 'instruction' => ''];
            } elseif (is_array($agent) && isset($agent['name'])) {
                // New format: {name, instruction}
                $agents[] = [
                    'name' => $agent['name'],
                    'instruction' => $agent['instruction'] ?? '',
                ];
            }
        }

        $agents = array_values(array_filter($agents, fn (array $a): bool => $a['name'] !== ''));

        return ['agents' => $agents];
    }

    private function buildRouterSystemPrompt(): string
    {
        $descriptions = $this->agentPool->getDescriptions();

        $agentList = '';
        foreach ($descriptions as $name => $description) {
            $agentList .= "- {$name}: {$description}\n";
        }

        return <<<PROMPT
Tu és o Maestro, um orquestrador inteligente de agentes. A tua tarefa é analisar o pedido do utilizador e decidir qual(is) agente(s) invocar.

## Agentes Disponíveis
{$agentList}
## Instruções
1. Analisa o pedido do utilizador.
2. Decide qual(is) agente(s) são mais adequados para a tarefa.
3. Se a tarefa requer múltiplos agentes em sequência (pipeline), lista-os na ordem de execução.
4. Para cada agente, fornece uma instrução específica que descreve o que esse agente deve fazer.
5. IMPORTANTE: Quando há um pipeline, o output do agente anterior é passado como input ao seguinte. A instrução de cada agente é PREPENDED ao input que recebe. Por isso, inclui instruções claras para cada agente, especialmente para agentes que recebem output de outros.

Responde APENAS com JSON válido no seguinte formato:

{"agents": [{"name": "agent1", "instruction": "instrução específica para este agente"}, {"name": "agent2", "instruction": "instrução específica para este agente"}]}

## Exemplos

Pedido: "cria um texto sobre robótica e traduz para inglês"
Resposta: {"agents": [{"name": "summarizer", "instruction": "cria um pequeno texto sobre robótica"}, {"name": "translator", "instruction": "traduz o seguinte texto para inglês"}]}

Pedido: "resume este artigo"
Resposta: {"agents": [{"name": "summarizer", "instruction": "resume este artigo"}]}

## Regras
- O campo "agents" é obrigatório e deve conter pelo menos um agente.
- Cada agente DEVE ter "name" e "instruction".
- A "instruction" do primeiro agente é a tarefa principal.
- A "instruction" dos agentes seguintes deve incluir o que fazer com o output do agente anterior (ex: "traduz o seguinte texto para inglês").
- Se nenhum agente for adequado, retorna: {"agents": []}
- Responde APENAS com JSON, sem texto adicional.
PROMPT;
    }

    private function executeSingleAgent(
        string $agentName,
        string $message,
        array $history,
        ?callable $progressCallback,
        float $startTime,
    ): OrchestrationResult {
        try {
            $agent = $this->agentPool->get($agentName);
            $response = $agent->handle($message, $history, $progressCallback);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::channel('maestro')->info('Maestro: agente único concluído', [
                'agent' => $agentName,
                'duration_ms' => $duration,
                'input_tokens' => $response->inputTokens,
                'output_tokens' => $response->outputTokens,
            ]);

            return OrchestrationResult::success(
                response: $response->text,
                inputTokens: $response->inputTokens,
                outputTokens: $response->outputTokens,
                history: [
                    [
                        'name' => $agentName,
                        'input' => $message,
                        'output' => $response->text,
                        'tokens' => [
                            'input' => $response->inputTokens,
                            'output' => $response->outputTokens,
                        ],
                        'duration_ms' => $response->metadata['duration_ms'] ?? $duration,
                    ],
                ],
                metadata: ['total_duration_ms' => $duration, 'routing' => 'single'],
            );
        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::channel('maestro')->error('Maestro: agente falhou', [
                'agent' => $agentName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => $duration,
            ]);

            return OrchestrationResult::failure(
                error: "Agente '{$agentName}' falhou: {$e->getMessage()}",
            );
        }
    }

    private function executePipeline(
        array $agents,
        string $originalMessage,
        ?callable $progressCallback,
        float $startTime,
    ): OrchestrationResult {
        $pipeline = new Pipeline;
        $firstInstruction = ! empty($agents[0]['instruction']) ? $agents[0]['instruction'] : $originalMessage;

        // Build agent steps with per-agent instructions
        $agentSteps = array_map(fn (array $a): array => [
            'name' => $a['name'],
            'instruction' => $a['instruction'] ?? '',
        ], $agents);

        $result = $pipeline->execute($agentSteps, $firstInstruction, $this->agentPool, $progressCallback);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $metadata = array_merge($result->metadata, [
            'total_duration_ms' => $duration,
            'routing' => 'pipeline',
        ]);

        if ($result->success) {
            return OrchestrationResult::success(
                response: $result->response,
                inputTokens: $result->totalInputTokens,
                outputTokens: $result->totalOutputTokens,
                history: $result->agentHistory,
                metadata: $metadata,
            );
        }

        return OrchestrationResult::failure(
            error: $result->error ?? 'Pipeline falhou',
            partialHistory: $result->agentHistory,
            inputTokens: $result->totalInputTokens,
            outputTokens: $result->totalOutputTokens,
        );
    }
}
