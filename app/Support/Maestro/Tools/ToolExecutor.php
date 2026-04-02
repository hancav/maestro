<?php

namespace App\Support\Maestro\Tools;

use App\Support\Maestro\Contracts\Tool;
use App\Support\Maestro\DTOs\ToolResult;

class ToolExecutor
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function register(Tool $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function execute(string $toolName, array $input): ToolResult
    {
        if (! $this->has($toolName)) {
            return ToolResult::failure("Unknown tool: {$toolName}");
        }

        try {
            return $this->tools[$toolName]->execute($input);
        } catch (\Exception $e) {
            return ToolResult::failure($e->getMessage());
        }
    }

    public function has(string $toolName): bool
    {
        return isset($this->tools[$toolName]);
    }

    /** @return list<array{name: string, description: string, input_schema: array}> */
    public function getAvailableTools(): array
    {
        return array_values(array_map(
            fn (Tool $tool): array => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'input_schema' => $tool->inputSchema(),
            ],
            $this->tools,
        ));
    }

    /** @return list<array{toolSpec: array{name: string, description: string, inputSchema: array{json: array}}}> */
    public function getAwsBedrockTools(): array
    {
        return array_values(array_map(
            fn (Tool $tool): array => [
                'toolSpec' => [
                    'name' => $tool->name(),
                    'description' => $tool->description(),
                    'inputSchema' => [
                        'json' => $tool->inputSchema(),
                    ],
                ],
            ],
            $this->tools,
        ));
    }
}
