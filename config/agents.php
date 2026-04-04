<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maestro Configuration
    |--------------------------------------------------------------------------
    */

    'maestro' => [
        'provider' => env('MAESTRO_PROVIDER', 'aws-bedrock'),
        'model' => env('MAESTRO_MODEL', 'us.amazon.nova-pro-v1:0'),
        'temperature' => 0.3,
        'max_tokens' => 1024,
        'system_prompt' => 'Tu és o Maestro, um orquestrador inteligente de agentes de IA.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'aws-bedrock' => [
            'region' => env('AWS_BEDROCK_REGION', 'us-east-1'),
            'bearer_token' => env('AWS_BEDROCK_BEARER_TOKEN'),
            'max_tool_iterations' => 6,
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'api_version' => '2023-06-01',
            'max_tool_iterations' => 10,
        ],
        'ollama' => [
            'base_url' => env('OLLAMA_URL', 'http://localhost:11434'),
            'timeout' => 120,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Definitions
    |--------------------------------------------------------------------------
    |
    | Each agent is defined in its own file under config/agents/.
    | To add a new agent, create a new file: config/agents/{name}.php
    | The agent will be automatically available to the Maestro.
    |
    */

    'agents' => [
        'summarizer' => require __DIR__.'/agents/summarizer.php',
        'translator' => require __DIR__.'/agents/translator.php',
        'writer' => require __DIR__.'/agents/writer.php',
        'mailer' => require __DIR__.'/agents/mailer.php',
    ],

];
