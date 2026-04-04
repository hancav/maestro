<?php

namespace App\Support\Maestro\Tools;

use App\Support\Maestro\Contracts\Tool;
use App\Support\Maestro\DTOs\ToolResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailTool implements Tool
{
    public function name(): string
    {
        return 'send_email';
    }

    public function description(): string
    {
        return 'Sends a professional email with optional PDF attachment. The email body should be in HTML format.';
    }

    /** @return array{type: string, properties: array, required: array} */
    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'to' => [
                    'type' => 'string',
                    'description' => 'Ignored - emails are always sent to ADMIN_EMAIL from .env configuration',
                ],
                'subject' => [
                    'type' => 'string',
                    'description' => 'The email subject line',
                ],
                'body_html' => [
                    'type' => 'string',
                    'description' => 'The email body in HTML format. Use <p>, <ul>, <li>, <strong> tags for formatting.',
                ],
                'pdf_path' => [
                    'type' => 'string',
                    'description' => 'Optional path to a PDF file to attach. Should be an absolute path or relative to storage/app/public.',
                ],
                'from_name' => [
                    'type' => 'string',
                    'description' => 'The sender name. Defaults to "Maestro".',
                ],
            ],
            'required' => ['subject', 'body_html'],
        ];
    }

    public function execute(array $input): ToolResult
    {
        $subject = $input['subject'] ?? '';
        $bodyHtml = $input['body_html'] ?? '';
        $pdfPath = $input['pdf_path'] ?? null;
        $fromName = $input['from_name'] ?? 'Maestro';
        
        // Always use ADMIN_EMAIL from .env (ignore 'to' parameter)
        $to = env('ADMIN_EMAIL');

        // Validate admin email is configured
        if (! $to || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Log::channel('maestro')->error('SendEmailTool: ADMIN_EMAIL not configured or invalid', [
                'admin_email' => $to,
            ]);
            return ToolResult::failure('ADMIN_EMAIL não está configurado ou é inválido no .env');
        }

        if (empty(trim($subject))) {
            Log::channel('maestro')->warning('SendEmailTool: empty subject', [
                'subject' => $subject,
            ]);
            return ToolResult::failure('O assunto do email não pode estar vazio.');
        }

        if (empty(trim($bodyHtml))) {
            Log::channel('maestro')->warning('SendEmailTool: empty body', [
                'body_length' => strlen($bodyHtml),
            ]);
            return ToolResult::failure('O corpo do email não pode estar vazio.');
        }

        try {
            Log::channel('maestro')->info('SendEmailTool: preparando email', [
                'to' => $to,
                'subject' => $subject,
                'from_name' => $fromName,
                'has_pdf' => ! empty($pdfPath),
                'pdf_path' => $pdfPath,
            ]);

            $emailHtml = $this->buildEmailHtml($bodyHtml);

            // Build attachments array
            $attachments = [];
            if ($pdfPath) {
                // Resolve relative paths
                $resolvedPath = $pdfPath;
                if (strpos($pdfPath, '/storage/') === 0) {
                    // Convert /storage/... to absolute path
                    $resolvedPath = storage_path('app/public' . substr($pdfPath, 8));
                }

                Log::channel('maestro')->info('SendEmailTool: resolvendo caminho PDF', [
                    'original_path' => $pdfPath,
                    'resolved_path' => $resolvedPath,
                ]);

                if (file_exists($resolvedPath)) {
                    $attachments[] = $resolvedPath;
                    Log::channel('maestro')->info('SendEmailTool: PDF encontrado e adicionado', [
                        'pdf_path' => $resolvedPath,
                        'file_size' => filesize($resolvedPath),
                        'file_exists' => true,
                    ]);
                } else {
                    Log::channel('maestro')->warning('SendEmailTool: PDF path fornecido mas ficheiro não encontrado', [
                        'original_path' => $pdfPath,
                        'resolved_path' => $resolvedPath,
                        'file_exists' => false,
                    ]);
                }
            }

            // Send email
            Mail::html($emailHtml, function ($message) use ($to, $subject, $fromName, $attachments) {
                $message->to($to)
                    ->subject($subject)
                    ->from(config('mail.from.address'), $fromName);

                // Add attachments if any
                foreach ($attachments as $attachment) {
                    $message->attach($attachment);
                }
            });

            $attachmentInfo = ! empty($attachments)
                ? implode(', ', array_map('basename', $attachments))
                : 'nenhum';

            Log::channel('maestro')->info('SendEmailTool: email enviado com sucesso', [
                'to' => $to,
                'subject' => $subject,
                'from_name' => $fromName,
                'attachments' => $attachmentInfo,
                'timestamp' => now()->toIso8601String(),
            ]);

            return ToolResult::success([
                'to' => $to,
                'subject' => $subject,
                'from_name' => $fromName,
                'attachments' => $attachmentInfo,
                'message' => "Email enviado com sucesso para {$to}" . (! empty($attachments) ? " com anexos ({$attachmentInfo})" : ''),
            ]);
        } catch (\Exception $e) {
            Log::channel('maestro')->error('SendEmailTool: erro ao enviar email', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return ToolResult::failure("Erro ao enviar email: {$e->getMessage()}");
        }
    }

    private function buildEmailHtml(string $bodyHtml): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 14px; color: #1a1a1a; line-height: 1.6; }
        .email-container { max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff; }
        .header { border-bottom: 1px solid #e5e7eb; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: #1e3a5f; font-size: 18px; }
        .content { margin: 20px 0; }
        .content p { margin: 10px 0; }
        .content h2 { font-size: 16px; color: #2563eb; margin: 15px 0 10px 0; }
        .content h3 { font-size: 14px; color: #374151; margin: 12px 0 8px 0; }
        .content ul, .content ol { margin: 10px 0 10px 20px; }
        .content li { margin: 5px 0; }
        .content strong { color: #1e3a5f; }
        .footer { border-top: 1px solid #e5e7eb; padding-top: 15px; margin-top: 20px; font-size: 12px; color: #666; }
        .signature { margin-top: 20px; color: #999; font-size: 12px; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="content">
            {$bodyHtml}
        </div>
        <div class="signature">
            <p>—<br>Enviado por Maestro Agent Orchestrator</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
