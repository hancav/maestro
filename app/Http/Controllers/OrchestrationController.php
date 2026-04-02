<?php

namespace App\Http\Controllers;

use App\Support\Maestro\Agent;
use App\Support\Maestro\AgentPool;
use App\Support\Maestro\Contracts\LlmProvider;
use App\Support\Maestro\DTOs\AgentConfig;
use App\Support\Maestro\Maestro;
use App\Support\Maestro\Providers\AnthropicProvider;
use App\Support\Maestro\Providers\AwsBedrockProvider;
use App\Support\Maestro\Providers\OllamaProvider;
use App\Support\Maestro\Tools\ToolExecutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrchestrationController extends Controller
{
    public function orchestrate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string',
            'agents' => 'sometimes|array',
            'agents.*' => 'string',
            'history' => 'sometimes|array',
        ]);

        try {
            $maestro = $this->buildMaestro();

            $result = $maestro->orchestrate(
                message: $validated['message'],
                history: $validated['history'] ?? [],
            );

            return response()->json($result->toArray());
        } catch (\Exception $e) {
            Log::error('Orchestration API error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Erro interno. Tente novamente.',
            ], 500);
        }
    }

    private function buildMaestro(): Maestro
    {
        $agentConfigs = config('agents.agents', []);
        $maestroConfig = config('agents.maestro', []);

        $toolExecutor = new ToolExecutor;

        $providerFactory = function (AgentConfig $config) use ($toolExecutor): Agent {
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
            'model' => $maestroConfig['model'] ?? 'us.anthropic.claude-3-5-haiku-20241022-v1:0',
            'system_prompt' => $maestroConfig['system_prompt'] ?? 'Tu és o Maestro.',
            'temperature' => $maestroConfig['temperature'] ?? 0.3,
            'max_tokens' => $maestroConfig['max_tokens'] ?? 1024,
        ]);

        $routerProvider = $this->createProvider($routerAgentConfig->provider, $toolExecutor);

        $routerAgent = new Agent(
            config: $routerAgentConfig,
            provider: $routerProvider,
            toolExecutor: $toolExecutor,
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
}
