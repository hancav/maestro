# Agentes Disponíveis

Lista completa de agentes orquestrados pelo Maestro com suas descrições e ferramentas disponíveis.

---

## 📌 Summarizer

**Nome:** `summarizer`

**Descrição:** Sumariza textos longos em resumos concisos e estruturados.

**Modelo:** AWS Bedrock - Nova Lite v1.0

**Temperatura:** 0.3 (moderado)

**Max Tokens:** 2048

**Ferramentas Disponíveis:** Nenhuma

**Casos de Uso:**

- Resumir artigos longos
- Condensar documentos
- Extrair pontos-chave de textos

**Exemplo de Prompt:**

```
Resume em 3 frases o que é inteligência artificial.
```

---

## 🌐 Translator

**Nome:** `translator`

**Descrição:** Traduz textos entre idiomas. Suporta português, inglês, espanhol, francês e outros. Identifica o idioma de destino a partir do pedido.

**Modelo:** AWS Bedrock - Nova Lite v1.0

**Temperatura:** 0.2 (mais preciso)

**Max Tokens:** 4096

**Ferramentas Disponíveis:** Nenhuma

**Casos de Uso:**

- Traduzir textos para diferentes idiomas
- Manter tom e estilo original
- Suporte multilíngue

**Exemplo de Prompt:**

```
Traduz para inglês: Portugal é um país com uma história rica.
```

---

## 📄 Writer

**Nome:** `writer`

**Descrição:** Escreve relatórios profissionais em PDF a partir de textos ou sumários. Formata o conteúdo de forma corporativa e gera um ficheiro PDF descarregável.

**Modelo:** AWS Bedrock - Nova Lite v1.0

**Temperatura:** 0.3 (moderado)

**Max Tokens:** 4096

**Ferramentas Disponíveis:**

- `write_pdf_report` - Gera relatórios profissionais em PDF

**Casos de Uso:**

- Criar relatórios executivos
- Gerar documentos corporativos
- Formatar conteúdo em PDF profissional

**Exemplo de Prompt:**

```
Cria um resumo sobre energias renováveis e gera um relatório em PDF.
```

---

## � Researcher

**Nome:** `researcher`

**Descrição:** Investiga a fundo sobre um assunto ou tópico pesquisando na internet e elabora um texto detalhado mas conciso, profissional e objectivo.

**Modelo:** AWS Bedrock - Nova Lite v1.0

**Temperatura:** 0.4 (criativo mas factual)

**Max Tokens:** 3072

**Ferramentas Disponíveis:**

- `web_search` - Pesquisa na internet sobre um tópico

**Casos de Uso:**

- Investigar tópicos em profundidade
- Pesquisa sobre tendências e inovações
- Elaborar análises profissionais baseadas em fontes
- Gerar conteúdo informativo e actualizado

**Exemplo de Prompt:**

```
Pesquisa sobre computação quântica e elabora um texto profissional detalhado.
```

---

## �📧 Mailer

**Nome:** `mailer`

**Descrição:** Compõe emails profissionais e sucintos a partir de textos ou sumários. Pode anexar PDFs gerados pelo writer. Envia emails com formatting corporativo.

**Modelo:** AWS Bedrock - Nova Lite v1.0

**Temperatura:** 0.3 (moderado)

**Max Tokens:** 2048

**Ferramentas Disponíveis:**

- `send_email` - Envia emails profissionais com anexos opcionais

**Casos de Uso:**

- Compor emails profissionais e sucintos
- Anexar PDFs a emails
- Enviar relatórios por email
- Comunicações corporativas

**Exemplo de Prompt:**

```
Faz um resumo sobre cibersegurança, escreve um relatório profissional e envia por email.
```

---

## 🔄 Pipelines Recomendados

### Pipeline 1: Resumo + Relatório + Email

```
Prompt: "Faz um resumo sobre cibersegurança, escreve um relatório profissional e envia por email."

summarizer → writer → mailer
```

### Pipeline 2: Pesquisa + Relatório + Email

```
Prompt: "Pesquisa sobre tendências em IA, cria um relatório profissional e envia por email."

researcher → writer → mailer
```

### Pipeline 3: Pesquisa + Resumo + Relatório

```
Prompt: "Pesquisa sobre blockchain, resume os pontos-chave e cria um relatório PDF."

researcher → summarizer → writer
```

### Pipeline 4: Resumo + Relatório

```
Prompt: "Resume o artigo sobre IA e cria um relatório em PDF."

summarizer → writer
```

### Pipeline 5: Tradução + Relatório + Email

```
Prompt: "Traduz um artigo para inglês, cria um relatório e envia por email."

translator → writer → mailer
```

### Pipeline 6: Agente Único

```
Prompt: "Traduz para português: Hello, how are you?"

translator (únicamente)

---

Prompt: "Pesquisa sobre cibersegurança e elabora um texto profissional."

researcher (únicamente)

```

Prompt: "Traduz para português: Hello, how are you?"

translator (únicamente)

```

---

## 📊 Tabela Comparativa

| Agente     | Modelo    | Ferramentas  | Complexidade |
| ---------- | --------- | ------------ | ------------ |
| Summarizer | Nova Lite | ✗            | Baixa        |
| Translator | Nova Lite | ✗            | Baixa        |
| Researcher | Nova Lite | web_search   | Média        |
| Writer     | Nova Lite | write_pdf_report | Média        |
| Mailer     | Nova Lite | send_email   | Média        |

---

## 🧠 Routing Inteligente

O Maestro usa um **router agent** inteligente que:

1. **Analisa** o pedido do utilizador
2. **Decide** qual(is) agente(s) invocar
3. **Ordena** os agentes em pipeline se necessário
4. **Passa** o output de um agente como input do seguinte

Este sistema permite criar workflows complexos a partir de prompts naturais!
```
