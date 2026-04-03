<?php

return [
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
];
