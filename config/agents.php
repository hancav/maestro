<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maestro Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Maestro orchestrator agent. The Maestro uses its
    | own LLM to analyze tasks and decide which agents to invoke.
    |
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
    |
    | Settings for each LLM provider. Credentials and connection details
    | are loaded from environment variables.
    |
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
    | Declarative agent configurations. Each agent is defined by its name,
    | description, provider, model, system prompt, and optional tools.
    | Add or remove agents by editing this array — no code changes needed.
    |
    */

    'agents' => [
        'summarizer' => [
            'name' => 'summarizer',
            'description' => 'Sumariza textos longos em resumos concisos e estruturados.',
            'provider' => 'aws-bedrock',
            'model' => 'us.amazon.nova-lite-v1:0',
            'system_prompt' => 'Tu és um agente especializado em sumarização de textos. '
                .'Produz resumos concisos, claros e bem estruturados, mantendo os pontos principais. '
                .'Responde sempre em português.',
            'temperature' => 0.3,
            'max_tokens' => 2048,
            'tools' => [],
        ],
        'translator' => [
            'name' => 'translator',
            'description' => 'Traduz textos entre idiomas, com foco em português, inglês e espanhol.',
            'provider' => 'aws-bedrock',
            'model' => 'us.amazon.nova-lite-v1:0',
            'system_prompt' => 'Tu és um agente especializado em tradução de textos. '
                .'Traduz com precisão mantendo o tom e estilo do original. '
                .'Se o idioma de destino não for especificado, traduz para português.',
            'temperature' => 0.2,
            'max_tokens' => 4096,
            'tools' => [],
        ],
    ],

];
