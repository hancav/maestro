<?php

namespace App\Support\Maestro;

use App\Support\Maestro\DTOs\OrchestrationResult;
use Illuminate\Support\Facades\Log;

class Pipeline
{
    /**
     * @param  list<array{name: string, instruction: string}|string>  $agentSteps  Agent names or {name, instruction} arrays
     *
     * @throws \InvalidArgumentException
     */
    public function execute(
        array $agentSteps,
        string $initialMessage,
        AgentPool $pool,
        ?callable $progressCallback = null,
    ): OrchestrationResult {
        if (empty($agentSteps)) {
            throw new \InvalidArgumentException('Pipeline requires at least one agent');
        }

        // Normalize steps to [{name, instruction}]
        $steps = array_map(function (mixed $step): array {
            if (is_string($step)) {
                return ['name' => $step, 'instruction' => ''];
            }

            return [
                'name' => $step['name'] ?? $step,
                'instruction' => $step['instruction'] ?? '',
            ];
        }, $agentSteps);

        $pipelineStart = microtime(true);
        $currentInput = $initialMessage;
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $agentHistory = [];
        $totalAgents = count($steps);

        Log::channel('maestro')->info('=== PIPELINE STARTED ===', [
            'steps' => $steps,
            'total_steps' => $totalAgents,
            'initial_message_preview' => mb_substr($initialMessage, 0, 300),
        ]);

        foreach ($steps as $index => $step) {
            $agentName = $step['name'];
            $instruction = $step['instruction'];
            $stepNumber = $index + 1;

            // For steps after the first, prepend instruction to the previous output
            if ($stepNumber > 1 && ! empty($instruction)) {
                $currentInput = "{$instruction}:\n\n{$currentInput}";
            }

            Log::channel('maestro')->info("=== PIPELINE STEP {$stepNumber}/{$totalAgents} START: {$agentName} ===", [
                'agent' => $agentName,
                'instruction' => $instruction,
                'input_preview' => mb_substr($currentInput, 0, 300),
            ]);

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
                    'instruction' => $instruction,
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

                Log::channel('maestro')->info("=== PIPELINE STEP {$stepNumber}/{$totalAgents} COMPLETE: {$agentName} ===", [
                    'agent' => $agentName,
                    'step' => $stepNumber,
                    'duration_ms' => $stepDuration,
                    'input_tokens' => $response->inputTokens,
                    'output_tokens' => $response->outputTokens,
                    'input_preview' => mb_substr($agentHistory[count($agentHistory) - 1]['input'], 0, 300),
                    'output_preview' => mb_substr($response->text, 0, 300),
                ]);
            } catch (\Exception $e) {
                $stepDuration = round((microtime(true) - ($stepStart ?? $pipelineStart)) * 1000, 2);

                Log::channel('maestro')->error("=== PIPELINE STEP {$stepNumber}/{$totalAgents} FAILED: {$agentName} ===", [
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

        Log::channel('maestro')->info('=== PIPELINE COMPLETED ===', [
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
