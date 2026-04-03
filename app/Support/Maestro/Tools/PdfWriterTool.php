<?php

namespace App\Support\Maestro\Tools;

use App\Support\Maestro\Contracts\Tool;
use App\Support\Maestro\DTOs\ToolResult;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PdfWriterTool implements Tool
{
    public function name(): string
    {
        return 'write_pdf_report';
    }

    public function description(): string
    {
        return 'Generates a professional PDF report from HTML content. Returns the file path and download URL of the generated PDF.';
    }

    /** @return array{type: string, properties: array, required: array} */
    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'The title of the report',
                ],
                'content_html' => [
                    'type' => 'string',
                    'description' => 'The HTML content of the report body. Use <h2>, <h3>, <p>, <ul>, <li>, <table> tags for structure.',
                ],
                'author' => [
                    'type' => 'string',
                    'description' => 'The author name for the report header. Defaults to "Maestro".',
                ],
            ],
            'required' => ['title', 'content_html'],
        ];
    }

    public function execute(array $input): ToolResult
    {
        $title = $input['title'] ?? 'Relatório';
        $contentHtml = $input['content_html'] ?? '';
        $author = $input['author'] ?? 'Maestro';

        if (empty(trim($contentHtml))) {
            return ToolResult::failure('O conteúdo HTML do relatório está vazio.');
        }

        try {
            $date = now()->format('d/m/Y H:i');
            $filename = 'report-'.Str::slug($title).'-'.now()->format('Ymd-His').'.pdf';
            $directory = storage_path('app/public/reports');

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $filepath = "{$directory}/{$filename}";

            $html = $this->buildReportHtml($title, $contentHtml, $author, $date);

            Pdf::loadHTML($html)
                ->setPaper('a4')
                ->save($filepath);

            $downloadUrl = "/storage/reports/{$filename}";

            Log::channel('maestro')->info('=== PDF REPORT GENERATED ===', [
                'title' => $title,
                'filename' => $filename,
                'filepath' => $filepath,
                'size_bytes' => filesize($filepath),
            ]);

            return ToolResult::success([
                'filename' => $filename,
                'filepath' => $filepath,
                'download_url' => $downloadUrl,
                'title' => $title,
                'message' => "Relatório PDF '{$title}' gerado com sucesso.",
            ]);
        } catch (\Exception $e) {
            Log::channel('maestro')->error('=== PDF GENERATION FAILED ===', [
                'title' => $title,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::failure("Erro ao gerar PDF: {$e->getMessage()}");
        }
    }

    private function buildReportHtml(string $title, string $contentHtml, string $author, string $date): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #1a1a1a; line-height: 1.6; margin: 40px; }
        .header { border-bottom: 2px solid #2563eb; padding-bottom: 15px; margin-bottom: 30px; }
        .header h1 { font-size: 22pt; color: #1e3a5f; margin: 0 0 5px 0; }
        .header .meta { font-size: 9pt; color: #666; }
        h2 { font-size: 14pt; color: #2563eb; margin-top: 25px; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; }
        h3 { font-size: 12pt; color: #374151; margin-top: 20px; }
        p { margin: 8px 0; text-align: justify; }
        ul, ol { margin: 8px 0 8px 20px; }
        li { margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th { background: #f3f4f6; padding: 8px; text-align: left; border: 1px solid #d1d5db; font-size: 10pt; }
        td { padding: 8px; border: 1px solid #d1d5db; font-size: 10pt; }
        .footer { margin-top: 40px; padding-top: 15px; border-top: 1px solid #e5e7eb; font-size: 8pt; color: #999; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{$title}</h1>
        <div class="meta">{$author} &middot; {$date}</div>
    </div>
    {$contentHtml}
    <div class="footer">Gerado por Maestro Agent Orchestrator &middot; {$date}</div>
</body>
</html>
HTML;
    }
}
