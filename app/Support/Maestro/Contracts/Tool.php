<?php

namespace App\Support\Maestro\Contracts;

use App\Support\Maestro\DTOs\ToolResult;

interface Tool
{
    public function name(): string;

    public function description(): string;

    /** @return array{type: string, properties: array, required?: array} */
    public function inputSchema(): array;

    public function execute(array $input): ToolResult;
}
