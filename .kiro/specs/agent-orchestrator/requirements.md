# Documento de Requisitos — Agent Orchestrator

## Introdução

O Agent Orchestrator é um novo projeto Laravel standalone que implementa um orquestrador multi-agente genérico. Inspirado na arquitetura existente do backoffice RTP Notícias (que já possui providers LLM, sistema de execução de ferramentas e um padrão básico de multi-agente), este projeto generaliza e expande esses conceitos num sistema limpo e desacoplado.

O sistema é composto por um "Maestro" — um agente orquestrador que utiliza um LLM para decidir dinamicamente quais agentes invocar — e um pool de agentes declarativos, cada um com o seu próprio system prompt, modelo, configuração e ferramentas opcionais. Os agentes podem ser encadeados (o output de um alimenta o input do seguinte), permitindo pipelines complexos de processamento.

## Glossário

- **Maestro**: O agente orquestrador central que recebe tarefas/mensagens do utilizador e decide, via LLM, quais agentes do pool invocar e em que ordem.
- **Agent**: Uma unidade de processamento autónoma com system prompt, modelo LLM, configuração e ferramentas opcionais. Cada agente executa uma tarefa específica (tradução, sumarização, pesquisa, etc.).
- **AgentPool**: O registo central de todos os agentes disponíveis no sistema, carregado a partir de configuração.
- **Pipeline**: Uma sequência ordenada de agentes onde o output de cada agente alimenta o input do seguinte.
- **LlmProvider**: Interface que abstrai a comunicação com diferentes fornecedores de LLM (AWS Bedrock, Anthropic, Ollama).
- **ProviderResponse**: DTO que encapsula a resposta de um LLM, incluindo texto, tokens de input/output e metadados.
- **ToolExecutor**: Componente responsável por registar, despachar e executar ferramentas disponíveis para os agentes.
- **ToolResult**: DTO que encapsula o resultado da execução de uma ferramenta (sucesso/falha com dados/erro).
- **AgentConfig**: Estrutura de configuração declarativa que define um agente (nome, provider, modelo, system prompt, ferramentas, temperatura, max_tokens).
- **OrchestrationResult**: DTO que encapsula o resultado final de uma orquestração, incluindo a resposta final, o histórico de agentes invocados, tokens consumidos e metadados.
- **ProgressCallback**: Função callback opcional para streaming de progresso em tempo real durante a execução de agentes e ferramentas.

## Requisitos

### Requisito 1: Interface LlmProvider

**User Story:** Como programador, quero uma interface genérica para comunicar com diferentes fornecedores de LLM, para que o sistema seja agnóstico em relação ao provider utilizado.

#### Critérios de Aceitação

1. THE LlmProvider SHALL definir o método `sendMessage(messages, systemPrompt, model, config): ProviderResponse` para enviar mensagens a um LLM.
2. THE LlmProvider SHALL definir o método `getName(): string` para identificar o provider.
3. THE LlmProvider SHALL definir o método `supportsTools(): bool` para indicar se o provider suporta chamadas a ferramentas.
4. WHEN um provider recebe uma mensagem válida, THE LlmProvider SHALL retornar um ProviderResponse com texto, inputTokens, outputTokens e metadados.
5. IF um provider encontra um erro de comunicação, THEN THE LlmProvider SHALL lançar uma exceção com uma mensagem descritiva do erro.

### Requisito 2: Providers LLM (AWS Bedrock, Anthropic, Ollama)

**User Story:** Como programador, quero implementações concretas para AWS Bedrock, Anthropic e Ollama, para que o sistema suporte múltiplos fornecedores de LLM.

#### Critérios de Aceitação

1. THE AwsBedrockProvider SHALL implementar a interface LlmProvider e comunicar com a API AWS Bedrock Converse.
2. THE AnthropicProvider SHALL implementar a interface LlmProvider e comunicar com a API Anthropic Messages.
3. THE OllamaProvider SHALL implementar a interface LlmProvider e comunicar com a API Ollama (local ou remota).
4. WHEN um provider suporta ferramentas, THE LlmProvider SHALL executar o loop de tool-use até obter uma resposta final de texto ou atingir o limite máximo de iterações.
5. THE AwsBedrockProvider SHALL suportar progress callbacks para streaming de progresso em tempo real durante a execução.
6. THE AnthropicProvider SHALL suportar progress callbacks para streaming de progresso em tempo real durante a execução.

### Requisito 3: Sistema de Ferramentas (Tools)

**User Story:** Como programador, quero um sistema extensível de ferramentas que os agentes possam utilizar, para que cada agente tenha acesso às ferramentas necessárias para a sua tarefa.

#### Critérios de Aceitação

1. THE ToolExecutor SHALL registar ferramentas por nome e despachar a execução para a ferramenta correta com base no nome recebido.
2. WHEN uma ferramenta é executada com sucesso, THE ToolExecutor SHALL retornar um ToolResult com success=true e os dados resultantes.
3. IF uma ferramenta falha durante a execução, THEN THE ToolExecutor SHALL retornar um ToolResult com success=false e uma mensagem de erro descritiva.
4. THE ToolExecutor SHALL fornecer a lista de ferramentas disponíveis no formato compatível com a API do provider (Anthropic, AWS Bedrock).
5. WHEN um novo tipo de ferramenta é adicionado, THE ToolExecutor SHALL permitir o seu registo sem alteração do código existente do executor.
6. THE ToolResult SHALL encapsular sucesso, dados, erro e metadados, e fornecer métodos factory `success()` e `failure()`.

### Requisito 4: Configuração Declarativa de Agentes

**User Story:** Como programador, quero definir agentes através de ficheiros de configuração, para que seja fácil adicionar, remover ou modificar agentes sem alterar código.

#### Critérios de Aceitação

1. THE AgentConfig SHALL ser carregado a partir de ficheiros de configuração Laravel (config/agents/\*.php ou config/agents.php).
2. WHEN um agente é definido na configuração, THE AgentConfig SHALL incluir: nome, descrição, provider, modelo, system prompt, temperatura, max_tokens e lista de ferramentas opcionais.
3. THE AgentPool SHALL carregar todos os agentes definidos na configuração e disponibilizá-los ao Maestro por nome.
4. WHEN um novo agente é adicionado ao ficheiro de configuração, THE AgentPool SHALL disponibilizá-lo automaticamente sem alteração de código.
5. IF um agente referenciado não existir no AgentPool, THEN THE AgentPool SHALL lançar uma exceção com o nome do agente em falta.

### Requisito 5: Agente Individual (Agent)

**User Story:** Como programador, quero que cada agente seja uma unidade autónoma de processamento com o seu próprio contexto, para que possa executar tarefas especializadas de forma independente.

#### Critérios de Aceitação

1. THE Agent SHALL receber uma mensagem/tarefa e produzir uma resposta utilizando o LLM configurado no seu AgentConfig.
2. THE Agent SHALL utilizar o system prompt definido na sua configuração ao comunicar com o LLM.
3. WHEN um agente tem ferramentas configuradas, THE Agent SHALL disponibilizá-las ao LLM durante a execução e processar o loop de tool-use.
4. THE Agent SHALL retornar um ProviderResponse com o texto da resposta, tokens consumidos e metadados.
5. IF o LLM do agente retorna uma resposta vazia, THEN THE Agent SHALL retornar uma mensagem de erro descritiva em vez de uma string vazia.
6. WHEN um ProgressCallback é fornecido, THE Agent SHALL emitir atualizações de progresso durante a execução.

### Requisito 6: Maestro (Orquestrador)

**User Story:** Como programador, quero um orquestrador inteligente que utilize um LLM para decidir quais agentes invocar, para que o routing de tarefas seja dinâmico e não codificado manualmente.

#### Critérios de Aceitação

1. WHEN o Maestro recebe uma tarefa/mensagem do utilizador, THE Maestro SHALL utilizar o seu próprio LLM para analisar a tarefa e decidir qual(is) agente(s) invocar.
2. THE Maestro SHALL receber no seu system prompt a lista de agentes disponíveis com os seus nomes e descrições.
3. THE Maestro SHALL produzir uma decisão de routing estruturada (JSON) que indica o(s) agente(s) a invocar e a ordem de execução.
4. WHEN o Maestro decide invocar um único agente, THE Maestro SHALL delegar a tarefa ao agente selecionado e retornar a resposta.
5. WHEN o Maestro decide invocar múltiplos agentes em sequência, THE Maestro SHALL executar o pipeline passando o output de cada agente como input do seguinte.
6. WHEN todos os agentes do pipeline terminam, THE Maestro SHALL compilar o OrchestrationResult final com a resposta, histórico de agentes invocados e tokens totais consumidos.
7. IF o Maestro não consegue determinar qual agente invocar, THEN THE Maestro SHALL retornar uma mensagem informativa ao utilizador indicando que não foi possível processar a tarefa.
8. IF um agente falha durante a execução no pipeline, THEN THE Maestro SHALL registar o erro e decidir se continua com o próximo agente ou aborta o pipeline.

### Requisito 7: Encadeamento de Agentes (Pipeline)

**User Story:** Como programador, quero que os agentes possam ser encadeados em pipelines, para que tarefas complexas sejam decompostas em passos sequenciais.

#### Critérios de Aceitação

1. THE Pipeline SHALL executar uma lista ordenada de agentes onde o output de cada agente é passado como input ao agente seguinte.
2. WHEN um pipeline é executado, THE Pipeline SHALL manter um registo do output de cada agente na sequência.
3. THE Pipeline SHALL acumular os tokens consumidos por todos os agentes e incluí-los no OrchestrationResult.
4. IF um agente no pipeline falha, THEN THE Pipeline SHALL interromper a execução e retornar um OrchestrationResult parcial com o erro e os resultados obtidos até ao ponto de falha.
5. WHEN um ProgressCallback é fornecido, THE Pipeline SHALL emitir atualizações de progresso indicando qual agente está a ser executado e o progresso geral do pipeline.

### Requisito 8: OrchestrationResult

**User Story:** Como programador, quero um DTO que encapsule o resultado completo de uma orquestração, para que o consumidor tenha acesso a toda a informação relevante.

#### Critérios de Aceitação

1. THE OrchestrationResult SHALL conter a resposta final (texto), o total de tokens de input, o total de tokens de output e metadados.
2. THE OrchestrationResult SHALL conter o histórico de agentes invocados, incluindo o nome de cada agente, o seu input, output e tokens consumidos.
3. THE OrchestrationResult SHALL indicar se a orquestração foi concluída com sucesso ou se ocorreu um erro.
4. THE OrchestrationResult SHALL fornecer um método `toArray()` para serialização em formato JSON.

### Requisito 9: Serialização e Desserialização de Configuração de Agentes

**User Story:** Como programador, quero que a configuração de agentes possa ser serializada e desserializada de forma fiável, para que a configuração seja persistida e carregada corretamente.

#### Critérios de Aceitação

1. THE AgentConfig SHALL ser serializável para um array PHP associativo via método `toArray()`.
2. THE AgentConfig SHALL ser construível a partir de um array PHP associativo via método factory `fromArray(array)`.
3. FOR ALL AgentConfig válidos, serializar via `toArray()` e depois desserializar via `fromArray()` SHALL produzir um AgentConfig equivalente ao original (propriedade round-trip).
4. IF o array de configuração contém campos obrigatórios em falta, THEN THE AgentConfig::fromArray() SHALL lançar uma exceção descritiva indicando os campos em falta.

### Requisito 10: API HTTP para Orquestração

**User Story:** Como programador, quero expor a funcionalidade de orquestração via API HTTP, para que sistemas externos possam submeter tarefas ao Maestro.

#### Critérios de Aceitação

1. WHEN um pedido POST é recebido no endpoint de orquestração com uma mensagem/tarefa válida, THE API SHALL delegar ao Maestro e retornar o OrchestrationResult em formato JSON.
2. IF o pedido não contém uma mensagem/tarefa, THEN THE API SHALL retornar um erro HTTP 422 com uma mensagem de validação descritiva.
3. IF ocorre um erro interno durante a orquestração, THEN THE API SHALL retornar um erro HTTP 500 com uma mensagem genérica sem expor detalhes internos.
4. THE API SHALL aceitar parâmetros opcionais para especificar agente(s) a invocar diretamente, ignorando a decisão do Maestro.
5. WHEN um ProgressCallback é suportado pelo cliente (ex: SSE ou WebSocket), THE API SHALL transmitir atualizações de progresso em tempo real durante a orquestração.

### Requisito 11: Logging e Observabilidade

**User Story:** Como programador, quero que todas as operações de orquestração sejam registadas em log, para que possa diagnosticar problemas e monitorizar o comportamento do sistema.

#### Critérios de Aceitação

1. WHEN o Maestro recebe uma tarefa, THE Maestro SHALL registar em log a tarefa recebida, a decisão de routing e os agentes selecionados.
2. WHEN um agente é executado, THE Agent SHALL registar em log o input recebido, o output produzido e os tokens consumidos.
3. IF um erro ocorre durante a orquestração, THEN THE Maestro SHALL registar em log o erro com contexto suficiente para diagnóstico (agente, input, stack trace).
4. THE OrchestrationResult SHALL incluir metadados de timing (duração total, duração por agente) para monitorização de performance.
