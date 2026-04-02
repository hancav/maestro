<?php

namespace App\Support\Maestro;

use App\Support\Maestro\DTOs\OrchestrationResult;
use Illuminate\Support\Facades\Log;

class Pipeline
{
    /**
     * @param  list<string>  $agentNames
     *
     * @throws \InvalidArgumentException
     */
    public function execute(
        array $agentNames,
        string $initialMessage,
        AgentPool $pool,
        ?callable $progressCallback = null,
    ): OrchestrationResult {
        if (empty($agentNames)) {
            throw new \InvalidArgumentException('Pipeline requires at least one agent');
        }

        $pipelineStart = microtime(true);
        $currentInput = $initialMessage;
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $agentHistory = [];
        $totalAgents = count($agentNames);

        Log::info('Pipeline started', [
            'agents' => $agentNames,
            'total_steps' => $totalAgents,
        ]);

        foreach ($agentNames as $index => $agentName) {
            $stepNumber = $index + 1;

            if ($progressCallback) {
                $progressCallback(
                    "Pipeline: passo {$stepNumber}/{$totalAgents} — agente '{$agentName}'",
                    ['type' => 'pipeline_step', 'step' => $stepNumber, 'total' => $totalAgents],
                );
            }

            try {
                $agent = $pool->get($agentName);
                $stepStart = microtime(true);

                $response = $agent->handle($currentInput, [], $progressCallback);

                $stepDuration = round((microtime(true) - $stepStart) * 1000, 2);

                $agentHistory[] = [
                    'name' => $agentName,
                    'input' => $currentInput,
                    'output' => $response->text,
                    'tokens' => [
                        'input' => $response->inputTokens,
                        'output' => $response->outputTokens,
                    ],
                    'duration_ms' => $stepDuration,
                ];

                $totalInputTokens += $response->inputTokens;
                $totalOutputTokens += $response->outputTokens;
                $currentInput = $response->text;

                Log::info('Pipeline step completed', [
                    'agent' => $agentName,
                    'step' => $stepNumber,
                    'duration_ms' => $stepDuration,
                    'input_tokens' => $response->inputTokens,
                    'output_tokens' => $response->outputTokens,
                ]);
            } catch (\Exception $e) {
                $stepDuration = round((microtime(true) - ($stepStart ?? $pipelineStart)) * 1000, 2);
                $totalDuration = round((microtime(true) - $pipelineStart) * 1000, 2);

                Log::error('Pipeline step failed', [
                    'agent' => $agentName,
                    'step' => $stepNumber,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'duration_ms' => $stepDuration,
                ]);

                return OrchestrationResult::failure(
                    error: "Pipeline falhou no agente '{$agentName}' (passo {$stepNumber}/{$totalAgents}): {$e->getMessage()}",
                    partialHistory: $agentHistory,
                    inputTokens: $totalInputTokens,
                    outputTokens: $totalOutputTokens,
                );
            }
        }

        $totalDuration = round((microtime(true) - $pipelineStart) * 1000, 2);

        Log::info('Pipeline completed', [
            'total_steps' => $totalAgents,
            'total_duration_ms' => $totalDuration,
            'total_input_tokens' => $totalInputTokens,
            'total_output_tokens' => $totalOutputTokens,
        ]);

        return OrchestrationResult::success(
            response: $currentInput,
            inputTokens: $totalInputTokens,
            outputTokens: $totalOutputTokens,
            history: $agentHistory,
            metadata: ['total_duration_ms' => $totalDuration],
        );
    }
}
