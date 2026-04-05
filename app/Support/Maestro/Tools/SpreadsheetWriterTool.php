<?php

namespace App\Support\Maestro\Tools;

use App\Support\Maestro\Contracts\Tool;
use App\Support\Maestro\DTOs\ToolResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SpreadsheetWriterTool implements Tool
{
    public function name(): string
    {
        return 'write_spreadsheet';
    }

    public function description(): string
    {
        return 'Generates spreadsheet documents (Excel/CSV) with tabular numeric data. Supports CSV format natively and XLSX if library is available. Returns the file path and download URL.';
    }

    /** @return array{type: string, properties: array, required: array} */
    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'The title of the spreadsheet document',
                ],
                'data' => [
                    'type' => 'array',
                    'description' => 'Array of row objects with numeric/tabular data. Each object represents one row.',
                    'items' => [
                        'type' => 'object',
                    ],
                ],
                'columns' => [
                    'type' => 'array',
                    'description' => 'Optional array of column names/headers. If not provided, keys from first row are used.',
                    'items' => [
                        'type' => 'string',
                    ],
                ],
                'format' => [
                    'type' => 'string',
                    'description' => 'File format: csv (default) or xlsx (if library available)',
                    'enum' => ['csv', 'xlsx'],
                ],
            ],
            'required' => ['title', 'data'],
        ];
    }

    public function execute(array $input): ToolResult
    {
        try {
            $title = $input['title'] ?? 'Relatório';
            $data = $input['data'] ?? [];
            $columns = $input['columns'] ?? null;
            $format = strtolower($input['format'] ?? 'csv');

            // Validate data
            if (! is_array($data) || empty($data)) {
                return ToolResult::failure('Os dados da folha de cálculo estão vazios. Forneça um array com pelo menos uma linha.');
            }

            // Ensure format is supported
            if (! in_array($format, ['csv', 'xlsx'])) {
                $format = 'csv';
            }

            // Try XLSX if requested, fallback to CSV if library not available
            if ($format === 'xlsx' && ! $this->hasExcelLibrary()) {
                Log::channel('maestro')->warning('Excel library not available, falling back to CSV format', [
                    'title' => $title,
                ]);
                $format = 'csv';
            }

            if ($format === 'xlsx') {
                return $this->generateXlsx($title, $data, $columns);
            } else {
                return $this->generateCsv($title, $data, $columns);
            }
        } catch (\Exception $e) {
            Log::channel('maestro')->error('Spreadsheet generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ToolResult::failure("Erro ao gerar folha de cálculo: {$e->getMessage()}");
        }
    }

    /**
     * Generate CSV spreadsheet
     */
    private function generateCsv(string $title, array $data, ?array $columns): ToolResult
    {
        $filename = 'spreadsheet-'.Str::slug($title).'-'.now()->format('Ymd-His').'.csv';
        $directory = storage_path('app/public/reports');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filepath = "{$directory}/{$filename}";

        // Extract columns from first row if not provided
        if ($columns === null && ! empty($data)) {
            $firstRow = $data[0];
            $columns = is_array($firstRow) ? array_keys($firstRow) : [];
        }

        // Generate CSV
        $handle = fopen($filepath, 'w');

        if ($handle === false) {
            return ToolResult::failure("Não foi possível criar o arquivo CSV: {$filename}");
        }

        // Write UTF-8 BOM for Excel compatibility
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

        // Write headers if available
        if (! empty($columns)) {
            fputcsv($handle, $columns, ';');
        }

        // Write data rows
        foreach ($data as $row) {
            if (is_array($row)) {
                // Use columns order if available, otherwise use values as-is
                if (! empty($columns)) {
                    $orderedRow = [];
                    foreach ($columns as $column) {
                        $orderedRow[] = $row[$column] ?? '';
                    }
                    fputcsv($handle, $orderedRow, ';');
                } else {
                    fputcsv($handle, $row, ';');
                }
            }
        }

        fclose($handle);

        $downloadUrl = "/storage/reports/{$filename}";

        Log::channel('maestro')->info('=== CSV SPREADSHEET GENERATED ===', [
            'title' => $title,
            'filename' => $filename,
            'filepath' => $filepath,
            'format' => 'csv',
            'rows' => count($data),
            'columns' => count($columns ?? []),
            'size_bytes' => filesize($filepath),
        ]);

        $responseData = [
            'title' => $title,
            'filename' => $filename,
            'filepath' => $downloadUrl,
            'format' => 'csv',
            'rows' => count($data),
            'columns' => count($columns ?? []),
            'download_url' => $downloadUrl,
            'message' => "Folha de cálculo CSV gerada com sucesso.",
        ];

        return ToolResult::success($responseData);
    }

    /**
     * Generate XLSX spreadsheet (requires library)
     */
    private function generateXlsx(string $title, array $data, ?array $columns): ToolResult
    {
        // This would require maatwebsite/excel or PhpSpreadsheet
        // For now, we'll use CSV as fallback
        Log::channel('maestro')->info('XLSX not fully implemented, using CSV fallback', [
            'title' => $title,
        ]);

        return $this->generateCsv($title, $data, $columns);
    }

    /**
     * Check if Excel library is available
     */
    private function hasExcelLibrary(): bool
    {
        return class_exists('Maatwebsite\Excel\Facades\Excel') ||
               class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet');
    }
}
