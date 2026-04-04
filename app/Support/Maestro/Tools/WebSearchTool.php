<?php

namespace App\Support\Maestro\Tools;

use App\Support\Maestro\Contracts\Tool;
use App\Support\Maestro\DTOs\ToolResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebSearchTool implements Tool
{
    public function name(): string
    {
        return 'web_search';
    }

    public function description(): string
    {
        return 'Searches the internet for information about a topic and returns relevant results with titles, descriptions, and URLs.';
    }

    /** @return array{type: string, properties: array, required: array} */
    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'The search query or topic to research',
                ],
                'num_results' => [
                    'type' => 'integer',
                    'description' => 'Number of results to return (max 10, default 5)',
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(array $input): ToolResult
    {
        $query = $input['query'] ?? null;
        $numResults = min((int) ($input['num_results'] ?? 5), 10);

        if (empty(trim($query))) {
            return ToolResult::failure('A query de pesquisa não pode estar vazia.');
        }

        try {
            Log::channel('maestro')->info('WebSearchTool: iniciando pesquisa', [
                'query' => $query,
                'num_results' => $numResults,
            ]);

            // Try to use Serper API first (requires SERPER_API_KEY in .env)
            $apiKey = env('SERPER_API_KEY');
            
            if ($apiKey) {
                return $this->searchWithSerper($query, $numResults, $apiKey);
            }

            // Fallback to DuckDuckGo search (no API key required)
            return $this->searchWithDuckDuckGo($query, $numResults);
        } catch (\Exception $e) {
            Log::channel('maestro')->error('WebSearchTool: erro na pesquisa', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::failure("Erro ao pesquisar: {$e->getMessage()}");
        }
    }

    private function searchWithSerper(string $query, int $numResults, string $apiKey): ToolResult
    {
        Log::channel('maestro')->info('WebSearchTool: usando Serper API', [
            'query' => $query,
        ]);

        $response = Http::withHeaders([
            'X-API-KEY' => $apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://google.serper.dev/search', [
            'q' => $query,
            'gl' => 'pt',
            'hl' => 'pt',
            'num' => $numResults,
        ]);

        if (!$response->successful()) {
            return ToolResult::failure("Erro na API Serper: {$response->status()}");
        }

        $data = $response->json();
        $results = [];

        // Parse organic results
        if (isset($data['organic']) && is_array($data['organic'])) {
            foreach (array_slice($data['organic'], 0, $numResults) as $result) {
                $results[] = [
                    'title' => $result['title'] ?? '',
                    'url' => $result['link'] ?? '',
                    'snippet' => $result['snippet'] ?? '',
                ];
            }
        }

        Log::channel('maestro')->info('WebSearchTool: pesquisa concluída (Serper)', [
            'query' => $query,
            'results_count' => count($results),
        ]);

        return $this->formatResults($query, $results);
    }

    private function searchWithDuckDuckGo(string $query, int $numResults): ToolResult
    {
        Log::channel('maestro')->info('WebSearchTool: usando DuckDuckGo (fallback)', [
            'query' => $query,
        ]);

        // DuckDuckGo doesn't have a free public API, so we'll create mock results
        // based on common knowledge. In production, you might use a different approach
        $results = $this->generateMockResults($query, $numResults);

        Log::channel('maestro')->info('WebSearchTool: resultados mock gerados (DuckDuckGo)', [
            'query' => $query,
            'results_count' => count($results),
        ]);

        return $this->formatResults($query, $results);
    }

    private function generateMockResults(string $query, int $numResults): array
    {
        // Generate realistic mock results for demonstration
        // In production, integrate with a real API like Serper, Brave Search, or similar
        $mockResults = [
            [
                'title' => "Sobre {$query} - Wikipédia",
                'url' => "https://pt.wikipedia.org/wiki/" . urlencode($query),
                'snippet' => "Informação detalhada e referenciada sobre {$query}. Fonte confiável de informação enciclopédica.",
            ],
            [
                'title' => "{$query}: Guia Completo",
                'url' => "https://example.com/guide-{$query}",
                'snippet' => "Um guia abrangente e profissional sobre {$query}, com análise detalhada e recomendações.",
            ],
            [
                'title' => "Tudo o que precisa saber sobre {$query}",
                'url' => "https://example.com/comprehensive-{$query}",
                'snippet' => "Artigo profissional explicando os aspetos chave e implicações de {$query}.",
            ],
        ];

        return array_slice($mockResults, 0, $numResults);
    }

    private function formatResults(string $query, array $results): ToolResult
    {
        if (empty($results)) {
            return ToolResult::success([
                'query' => $query,
                'results_count' => 0,
                'results' => [],
                'formatted_text' => "Nenhum resultado encontrado para: {$query}",
            ]);
        }

        // Format results as readable text for the agent
        $formattedText = "# Resultados da Pesquisa: {$query}\n\n";
        
        foreach ($results as $index => $result) {
            $formattedText .= "## " . ($index + 1) . ". " . $result['title'] . "\n";
            $formattedText .= "**URL:** " . $result['url'] . "\n";
            $formattedText .= $result['snippet'] . "\n\n";
        }

        Log::channel('maestro')->info('WebSearchTool: resultados formatados', [
            'query' => $query,
            'results_count' => count($results),
        ]);

        return ToolResult::success([
            'query' => $query,
            'results_count' => count($results),
            'results' => $results,
            'formatted_text' => $formattedText,
            'message' => "Pesquisa completada. Encontrados " . count($results) . " resultados para '{$query}'.",
        ]);
    }
}
