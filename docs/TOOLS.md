# Ferramentas Disponíveis

Lista completa de ferramentas disponíveis no Maestro com suas descrições, parâmetros e agentes que as utilizam.

---

## 🔧 write_pdf_report

**ID:** `write_pdf_report`

**Descrição:** Gera relatórios profissionais em PDF a partir de conteúdo HTML. Retorna o caminho do ficheiro e URL de download.

**Agentes com Acesso:**

- `writer` - Agente de escrita de relatórios

**Parâmetros:**

| Parâmetro      | Tipo   | Obrigatório | Descrição                           |
| -------------- | ------ | ----------- | ----------------------------------- |
| `title`        | string | ✅ Sim      | Título do relatório                 |
| `content_html` | string | ✅ Sim      | Conteúdo HTML do corpo do relatório |
| `author`       | string | ❌ Não      | Nome do autor (padrão: "Maestro")   |

**HTML Suportado:**

- `<h2>`, `<h3>` - Cabeçalhos de secções
- `<p>` - Parágrafos
- `<ul>`, `<ol>`, `<li>` - Listas
- `<table>`, `<tr>`, `<td>`, `<th>` - Tabelas
- `<strong>`, `<em>` - Formatação de texto

**Resposta de Sucesso:**

```json
{
    "filename": "report-titulo-20260404-145757.pdf",
    "filepath": "/Users/hugo.cavalaria/Github/maestro/storage/app/public/reports/report-titulo-20260404-145757.pdf",
    "download_url": "/storage/reports/report-titulo-20260404-145757.pdf",
    "title": "Título do Relatório",
    "message": "Relatório PDF 'Título' gerado com sucesso."
}
```

**Exemplo de Uso:**

O agente writer envia:

```
title: "Análise de Segurança Cibernética"
content_html: "<h2>Introdução</h2><p>Este relatório analisa...</p>"
author: "Maestro"
```

Resultado:

- 📄 PDF gerado em `/storage/reports/report-analise-de-seguranca-cibernetica-20260404-145757.pdf`
- 🔗 URL de download: `/storage/reports/report-analise-de-seguranca-cibernetica-20260404-145757.pdf`

**Logs:**

```
[maestro] === PDF REPORT GENERATED === {
  "title": "Análise de Segurança Cibernética",
  "filename": "report-analise-...-20260404-145757.pdf",
  "size_bytes": 45230
}
```

---

## � web_search

**ID:** `web_search`

**Descrição:** Pesquisa na internet por informação sobre um tópico e retorna resultados relevantes com títulos, descrições e URLs.

**Agentes com Acesso:**

- `researcher` - Agente de pesquisa e elaboração de análises

**Parâmetros:**

| Parâmetro     | Tipo    | Obrigatório | Descrição                                |
| ------------- | ------- | ----------- | ---------------------------------------- |
| `query`       | string  | ✅ Sim      | Termo de pesquisa ou tópico a investigar |
| `num_results` | integer | ❌ Não      | Número de resultados (máx 10, padrão: 5) |

**Resposta de Sucesso:**

```json
{
    "query": "computação quântica",
    "results_count": 5,
    "results": [
        {
            "title": "Sobre Computação Quântica - Wikipédia",
            "url": "https://pt.wikipedia.org/wiki/Computa%C3%A7%C3%A3o_qu%C3%A2ntica",
            "snippet": "Informação detalhada e referenciada sobre computação quântica..."
        },
        {
            "title": "Computação Quântica: Guia Completo",
            "url": "https://example.com/guide-quantum",
            "snippet": "Um guia abrangente sobre computação quântica e suas aplicações..."
        }
    ],
    "formatted_text": "# Resultados da Pesquisa: computação quântica\n\n## 1. Sobre Computação Quântica...",
    "message": "Pesquisa completada. Encontrados 5 resultados para 'computação quântica'."
}
```

**Exemplo de Uso:**

O agente researcher envia:

```
query: "computação quântica"
num_results: 5
```

Resultado:

- 🔍 Pesquisa realizada na internet
- 📝 Resultados formatados como texto legível
- 🔗 URLs de referência incluídas

**Logs:**

```
[maestro] WebSearchTool: iniciando pesquisa {
  "query": "computação quântica",
  "num_results": 5
}

[maestro] WebSearchTool: pesquisa concluída {
  "query": "computação quântica",
  "results_count": 5
}
```

**Provedores Suportados:**

1. **Serper API** (recomendado)
    - Requer `SERPER_API_KEY` no `.env`
    - Pesquisa com resultados de alta qualidade
    - Suporte para múltiplos idiomas e regiões

2. **Fallback DuckDuckGo** (sem API key)
    - Funciona sem configuração adicional
    - Gera resultados mock realistas (para demonstração)
    - Ideal para testes e desenvolvimento

**Configuração (Opcional):**

Para usar Serper API (melhor qualidade):

```env
SERPER_API_KEY=sua-chave-api-aqui
```

---

## �📧 send_email

**ID:** `send_email`

**Descrição:** Envia emails profissionais com conteúdo HTML formatado. Suporta anexação de PDFs e validação automática.

**Agentes com Acesso:**

- `mailer` - Agente de composição e envio de emails

**Parâmetros:**

| Parâmetro   | Tipo   | Obrigatório | Descrição                                         |
| ----------- | ------ | ----------- | ------------------------------------------------- |
| `subject`   | string | ✅ Sim      | Assunto do email                                  |
| `body_html` | string | ✅ Sim      | Corpo do email em HTML                            |
| `pdf_path`  | string | ❌ Não      | Caminho do PDF para anexar (relativo ou absoluto) |
| `from_name` | string | ❌ Não      | Nome do remetente (padrão: "Maestro")             |
| `to`        | string | ❌ Não      | Ignorado - sempre usa ADMIN_EMAIL do .env         |

**HTML Suportado:**

- `<p>` - Parágrafos
- `<h2>`, `<h3>` - Cabeçalhos
- `<ul>`, `<ol>`, `<li>` - Listas
- `<strong>`, `<em>` - Formatação
- Qualquer HTML simples válido

**Configuração:**

- **Destinatário:** `ADMIN_EMAIL` do ficheiro `.env`
- **Remetente:** `MAIL_FROM_ADDRESS` do ficheiro `.env`
- **Nome do Remetente:** Configurável ou padrão "Maestro"
- **SMTP:** Configurado em `MAIL_*` do `.env`

**Resposta de Sucesso:**

```json
{
    "to": "hugo.cavalaria.aux@gmail.com",
    "subject": "Relatório sobre Segurança Cibernética",
    "from_name": "Maestro",
    "attachments": "report-analise-seguranca-cibernetica-20260404-145757.pdf",
    "message": "Email enviado com sucesso para hugo.cavalaria.aux@gmail.com com anexos (report-analise-seguranca-cibernetica-20260404-145757.pdf)"
}
```

**Exemplo de Uso:**

O agente mailer envia:

```
subject: "Relatório sobre Segurança Cibernética"
body_html: "<p>Prezado Senhor,</p><p>Segue anexo o relatório solicitado...</p>"
pdf_path: "/storage/reports/report-analise-seguranca-cibernetica-20260404-145757.pdf"
from_name: "Maestro"
```

**Resolução de Caminhos:**

- `/storage/reports/file.pdf` → Converte para caminho absoluto automaticamente
- Caminhos absolutos são usados tal qual
- Caminhos relativos são resolvidos a partir de `storage/app/public`

**Logs:**

```
[maestro] SendEmailTool: preparando email {
  "to": "hugo.cavalaria.aux@gmail.com",
  "subject": "Relatório sobre Segurança Cibernética",
  "has_pdf": true,
  "pdf_path": "/storage/reports/report-..."
}

[maestro] SendEmailTool: PDF encontrado e adicionado {
  "pdf_path": "/abs/path/storage/app/public/reports/...",
  "file_size": 45230
}

[maestro] SendEmailTool: email enviado com sucesso {
  "to": "hugo.cavalaria.aux@gmail.com",
  "attachments": "report-...-20260404-145757.pdf"
}
```

**Validações:**

- ✅ Email ADMIN_EMAIL é validado
- ✅ Assunto obrigatório e não vazio
- ✅ Corpo HTML obrigatório e não vazio
- ✅ PDF só é anexado se ficheiro existir
- ❌ Falha se ADMIN_EMAIL não estiver configurado

---

## 🔄 Fluxos de Ferramentas

### Fluxo 1: Researcher → Writer → Mailer

```
1. Researcher usa web_search para pesquisar na internet
   ↓
2. Resultados são analisados e reformulados em texto profissional
   ↓
3. Writer usa write_pdf_report para gerar PDF
   ↓
4. PDF é salvo em /storage/reports/report-xxx.pdf
   ↓
5. Mailer usa send_email com pdf_path
   ↓
6. Email enviado com PDF anexado
```

### Fluxo 2: Writer → Mailer

```
1. Writer usa write_pdf_report para gerar PDF
   ↓
2. PDF é salvo em /storage/reports/report-xxx.pdf
   ↓
3. Mailer recebe o caminho do PDF
   ↓
4. Mailer usa send_email com pdf_path
   ↓
5. Email enviado com PDF anexado
```

### Fluxo 3: Pipeline Summarizer

```
1. Summarizer: Cria resumo (sem ferramentas)
   ↓
2. Writer: Usa write_pdf_report → Gera PDF
   ↓
3. Mailer: Usa send_email com PDF → Envia email
```

### Fluxo 4: Pipeline Researcher Único

```
1. Researcher usa web_search para pesquisar
   ↓
2. Elabora texto profissional e detalhado
   ↓
3. Resposta é enviada ao utilizador
```

---

## 📊 Tabela Resumida

| Tool               | Agente     | Entrada         | Saída          | Efeito                 |
| ------------------ | ---------- | --------------- | -------------- | ---------------------- |
| `web_search`       | researcher | Query           | JSON + Texto   | Pesquisa na internet   |
| `write_pdf_report` | writer     | HTML            | PDF File + URL | Cria ficheiro em disco |
| `send_email`       | mailer     | HTML + PDF Path | Email Enviado  | Envia via SMTP         |

---

## 🚀 Como Criar uma Nova Tool

Vê [docs/CREATING_TOOLS.md](CREATING_TOOLS.md) para instruções completas.

Process de desenvolvimento:

1. Criar classe em `app/Support/Maestro/Tools/MyTool.php`
2. Implementar interface `Tool`
3. Registar em `MaestroChat.php`
4. Adicionar ao array `tools` do agente
5. Testar via chat

---

## 🔐 Segurança

### write_pdf_report

- ✅ Ficheiros salvos em `storage/app/public/reports`
- ✅ Nomes de ficheiro sanitizados (slug)
- ✅ Logs completos das operações

### send_email

- ✅ Validação de email ADMIN_EMAIL
- ✅ Resoluções segurasdos caminhos de PDF
- ✅ Whitelist de caminhos permitidos (`/storage/...`)
- ✅ Logs detalhados de tentativas de envio
- ✅ Tratamento de erros robusto

---

## 📝 Notas de Implementação

- Sempre registar o resultado da ferramenta no log `maestro`
- Retornar `ToolResult::success()` ou `ToolResult::failure()`
- Incluir context útil nos logs para debugging
- Validar inputs antes de processar
- Tratar exceções graciosamente
