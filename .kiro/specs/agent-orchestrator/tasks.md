# Plano de Implementação: Agent Orchestrator

## Visão Geral

Implementação incremental do sistema multi-agente Maestro em Laravel, seguindo a ordem: DTOs → Contratos → ToolExecutor → Providers → Agent → AgentPool → Pipeline → Maestro → Config → API → UI → Logging. Cada passo constrói sobre o anterior, garantindo que não há código órfão.

## Tarefas

- [x]   1. Criar DTOs base (ProviderResponse, ToolResult, AgentConfig, OrchestrationResult)
    - [x] 1.1 Criar `app/Support/Maestro/DTOs/ProviderResponse.php`
        - Implementar construtor com `text`, `inputTokens`, `outputTokens`, `metadata`
        - Implementar `create()` factory method, `getTotalTokens()` e `toArray()`
        - _Requisitos: 1.4_

    - [x] 1.2 Criar `app/Support/Maestro/DTOs/ToolResult.php`
        - Implementar construtor com `success`, `data`, `error`, `metadata`
        - Implementar factory methods `success()` e `failure()`, `isSuccess()` e `toArray()`
        - _Requisitos: 3.2, 3.3, 3.6_

    - [ ]\* 1.3 Escrever teste de propriedade para ToolResult factory round-trip
        - **Propriedade 2: Round-trip dos factory methods do ToolResult**
        - **Valida: Requisitos 3.2, 3.3, 3.6**

    - [x] 1.4 Criar `app/Support/Maestro/DTOs/AgentConfig.php`
        - Implementar construtor com `name`, `description`, `provider`, `model`, `systemPrompt`, `temperature`, `maxTokens`, `tools`, `metadata`
        - Implementar `fromArray()` com validação de campos obrigatórios e `toArray()`
        - Lançar `\InvalidArgumentException` se campos obrigatórios estiverem em falta
        - _Requisitos: 4.2, 9.1, 9.2, 9.4_

    - [ ]\* 1.5 Escrever teste de propriedade para AgentConfig round-trip
        - **Propriedade 4: Round-trip de serialização do AgentConfig**
        - **Valida: Requisitos 4.2, 9.1, 9.2, 9.3**

    - [ ]\* 1.6 Escrever testes unitários para AgentConfig (campos em falta)
        - Testar que `fromArray()` lança exceção quando campos obrigatórios estão em falta
        - _Requisitos: 9.4_

    - [x] 1.7 Criar `app/Support/Maestro/DTOs/OrchestrationResult.php`
        - Implementar construtor com `response`, `success`, `totalInputTokens`, `totalOutputTokens`, `agentHistory`, `error`, `metadata`
        - Implementar factory methods `success()` e `failure()`, e `toArray()`
        - _Requisitos: 8.1, 8.2, 8.3, 8.4_

    - [ ]\* 1.8 Escrever teste de propriedade para OrchestrationResult serialização
        - **Propriedade 10: Serialização completa do OrchestrationResult**
        - **Valida: Requisitos 8.1, 8.2, 8.3, 8.4**

- [x]   2. Checkpoint — Verificar que todos os testes passam
    - Garantir que todos os testes passam, perguntar ao utilizador se surgirem dúvidas.

- [x]   3. Criar contratos (LlmProvider, Tool)
    - [x] 3.1 Criar `app/Support/Maestro/Contracts/LlmProvider.php`
        - Definir interface com `sendMessage()`, `getName()`, `supportsTools()`, `setProgressCallback()`
        - _Requisitos: 1.1, 1.2, 1.3_

    - [x] 3.2 Criar `app/Support/Maestro/Contracts/Tool.php`
        - Definir interface com `name()`, `description()`, `inputSchema()`, `execute()`
        - _Requisitos: 3.5_

- [x]   4. Implementar ToolExecutor
    - [x] 4.1 Criar `app/Support/Maestro/Tools/ToolExecutor.php`
        - Implementar `register()`, `execute()`, `has()`, `getAvailableTools()`, `getAwsBedrockTools()`
        - Retornar `ToolResult::failure()` para ferramentas não encontradas
        - Capturar exceções durante execução e retornar `ToolResult::failure()`
        - _Requisitos: 3.1, 3.2, 3.3, 3.4, 3.5_

    - [ ]\* 4.2 Escrever teste de propriedade para despacho do ToolExecutor
        - **Propriedade 1: Despacho correto do ToolExecutor**
        - **Valida: Requisitos 3.1**

    - [ ]\* 4.3 Escrever teste de propriedade para formato de ferramentas
        - **Propriedade 3: Conversão de formato de ferramentas para providers**
        - **Valida: Requisitos 3.4**

- [x]   5. Checkpoint — Verificar que todos os testes passam
    - Garantir que todos os testes passam, perguntar ao utilizador se surgirem dúvidas.

- [x]   6. Implementar Providers LLM
    - [x] 6.1 Criar `app/Support/Maestro/Providers/AwsBedrockProvider.php`
        - Implementar `LlmProvider` com comunicação via AWS Bedrock Converse API
        - Suportar loop de tool-use com limite máximo de iterações
        - Suportar progress callbacks
        - _Requisitos: 2.1, 2.4, 2.5_

    - [x] 6.2 Criar `app/Support/Maestro/Providers/AnthropicProvider.php`
        - Implementar `LlmProvider` com comunicação via Anthropic Messages API
        - Suportar loop de tool-use com limite máximo de iterações
        - Suportar progress callbacks
        - _Requisitos: 2.2, 2.4, 2.6_

    - [x] 6.3 Criar `app/Support/Maestro/Providers/OllamaProvider.php`
        - Implementar `LlmProvider` com comunicação via Ollama API (local/remota)
        - _Requisitos: 2.3_

- [x]   7. Implementar Agent
    - [x] 7.1 Criar `app/Support/Maestro/Agent.php`
        - Implementar construtor com `AgentConfig`, `LlmProvider`, `ToolExecutor`
        - Implementar `handle()` que envia mensagem ao LLM com system prompt da config
        - Retornar mensagem de fallback se resposta do LLM for vazia
        - Suportar progress callbacks
        - _Requisitos: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6_

    - [ ]\* 7.2 Escrever teste de propriedade para propagação de config do Agent
        - **Propriedade 6: Propagação de configuração do Agent para o Provider**
        - **Valida: Requisitos 5.2**

    - [ ]\* 7.3 Escrever testes unitários para Agent (resposta vazia)
        - Testar que resposta vazia do LLM retorna mensagem de fallback descritiva
        - _Requisitos: 5.5_

- [x]   8. Implementar AgentPool
    - [x] 8.1 Criar `app/Support/Maestro/AgentPool.php`
        - Implementar construtor que recebe configs de agentes e cria instâncias de Agent
        - Implementar `get()`, `has()`, `all()`, `getDescriptions()`
        - Lançar exceção se agente não encontrado
        - _Requisitos: 4.3, 4.4, 4.5_

    - [ ]\* 8.2 Escrever teste de propriedade para disponibilidade de agentes no AgentPool
        - **Propriedade 5: Disponibilidade de agentes no AgentPool**
        - **Valida: Requisitos 4.3, 4.4**

    - [ ]\* 8.3 Escrever testes unitários para AgentPool (agente inexistente)
        - Testar que `get()` lança exceção para agente não registado
        - _Requisitos: 4.5_

- [x]   9. Checkpoint — Verificar que todos os testes passam
    - Garantir que todos os testes passam, perguntar ao utilizador se surgirem dúvidas.

- [x]   10. Implementar Pipeline
    - [x] 10.1 Criar `app/Support/Maestro/Pipeline.php`
        - Implementar `execute()` que corre agentes em sequência
        - Passar output de cada agente como input do seguinte
        - Acumular tokens e registar histórico de cada passo
        - Interromper em caso de falha e retornar resultado parcial
        - Suportar progress callbacks
        - _Requisitos: 7.1, 7.2, 7.3, 7.4, 7.5_

    - [ ]\* 10.2 Escrever teste de propriedade para encadeamento e histórico do Pipeline
        - **Propriedade 8: Encadeamento e histórico do Pipeline**
        - **Valida: Requisitos 7.1, 7.2**

    - [ ]\* 10.3 Escrever teste de propriedade para acumulação de tokens no Pipeline
        - **Propriedade 9: Acumulação de tokens no Pipeline**
        - **Valida: Requisitos 6.6, 7.3**

    - [ ]\* 10.4 Escrever testes unitários para Pipeline (falha no meio)
        - Testar que falha de um agente retorna `OrchestrationResult::failure()` com histórico parcial
        - _Requisitos: 7.4_

- [x]   11. Implementar Maestro (Orquestrador)
    - [x] 11.1 Criar `app/Support/Maestro/Maestro.php`
        - Implementar construtor com `AgentPool` e `routerAgent`
        - Implementar `orchestrate()` que usa o LLM para decidir routing
        - Construir system prompt com lista de agentes disponíveis (nomes e descrições)
        - Suportar invocação de agente único ou pipeline sequencial
        - Compilar `OrchestrationResult` final com resposta, histórico e tokens
        - Retornar mensagem informativa se não conseguir determinar agente
        - Registar erro e decidir se continua ou aborta em caso de falha
        - _Requisitos: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8_

    - [ ]\* 11.2 Escrever teste de propriedade para completude do system prompt do Maestro
        - **Propriedade 7: Completude do system prompt do Maestro**
        - **Valida: Requisitos 6.2**

    - [ ]\* 11.3 Escrever testes unitários para Maestro (routing indeterminado e falha de agente)
        - Testar que routing indeterminado retorna mensagem informativa
        - Testar que falha de agente é tratada corretamente
        - _Requisitos: 6.7, 6.8_

- [x]   12. Checkpoint — Verificar que todos os testes passam
    - Garantir que todos os testes passam, perguntar ao utilizador se surgirem dúvidas.

- [x]   13. Criar ficheiro de configuração e endpoint API
    - [x] 13.1 Criar `config/agents.php`
        - Definir configuração do Maestro (provider, model, temperature, system_prompt)
        - Definir configuração dos providers (aws-bedrock, anthropic, ollama)
        - Definir agentes de exemplo (summarizer, researcher)
        - _Requisitos: 4.1, 4.2_

    - [x] 13.2 Criar `app/Http/Controllers/OrchestrationController.php` e rota API
        - Implementar endpoint POST para orquestração
        - Validar campo `message` obrigatório (422 se em falta)
        - Aceitar parâmetro opcional `agents` para invocação direta
        - Retornar `OrchestrationResult::toArray()` em JSON
        - Retornar erro 500 genérico sem detalhes internos em caso de falha
        - Registar rota em `routes/api.php`
        - _Requisitos: 10.1, 10.2, 10.3, 10.4_

    - [ ]\* 13.3 Escrever testes feature para API de orquestração
        - Testar pedido sem mensagem retorna 422
        - Testar erro interno retorna 500 sem detalhes
        - _Requisitos: 10.2, 10.3_

- [x]   14. Integrar MaestroChat com o orquestrador
    - [x] 14.1 Atualizar `app/Filament/Pages/MaestroChat.php`
        - Substituir resposta placeholder pela chamada ao `Maestro::orchestrate()`
        - Instanciar Maestro com AgentPool carregado de `config('agents')`
        - Passar progress callback para streaming de atualizações em tempo real
        - Atualizar contadores de tokens com dados do `OrchestrationResult`
        - _Requisitos: 6.1, 6.4, 6.5, 6.6, 10.5_

- [x]   15. Implementar logging e observabilidade
    - [x] 15.1 Adicionar logging ao Maestro, Agent e Pipeline
        - Registar em log a tarefa recebida, decisão de routing e agentes selecionados no Maestro
        - Registar em log input, output e tokens consumidos em cada Agent
        - Registar erros com contexto suficiente (agente, input, stack trace)
        - Incluir metadados de timing (duração total, duração por agente) no OrchestrationResult
        - _Requisitos: 11.1, 11.2, 11.3, 11.4_

- [x]   16. Checkpoint final — Verificar que todos os testes passam
    - Garantir que todos os testes passam, perguntar ao utilizador se surgirem dúvidas.

## Notas

- Tarefas marcadas com `*` são opcionais e podem ser ignoradas para um MVP mais rápido
- Cada tarefa referencia requisitos específicos para rastreabilidade
- Os checkpoints garantem validação incremental
- Testes de propriedade validam propriedades universais de correção
- Testes unitários validam exemplos específicos e edge cases
- Os providers LLM devem usar mocks nos testes para evitar dependências externas
