<?php

namespace App\Support\Maestro;

use Carbon\Carbon;

class ContextManager
{
    /**
     * Builds a contextualized system prompt with current temporal information.
     * This ensures all agents are aware of the current date/time/year.
     */
    public static function buildContextualSystemPrompt(string $basePrompt): string
    {
        $context = self::getCurrentContext();

        return "{$basePrompt}\n\n{$context}";
    }

    /**
     * Get current temporal context information.
     * This can be extended to include other contextual data in the future.
     */
    private static function getCurrentContext(): string
    {
        $now = Carbon::now();

        $contextLines = [
            '---',
            '## Contexto Temporal Actual',
            "- **Data actual**: {$now->format('d/m/Y')} ({$now->translatedFormat('l')})",
            "- **Hora actual**: {$now->format('H:i:s')}",
            "- **Ano**: {$now->year}",
            "- **Timezone**: {$now->timezone->getName()}",
            "- **Dia do ano**: {$now->dayOfYear}",
            '---',
        ];

        return implode("\n", $contextLines);
    }

    /**
     * Get context metadata (useful for logging/debugging).
     */
    public static function getContextMetadata(): array
    {
        $now = Carbon::now();

        return [
            'date' => $now->format('d/m/Y'),
            'time' => $now->format('H:i:s'),
            'year' => $now->year,
            'timezone' => $now->timezone->getName(),
            'day_of_year' => $now->dayOfYear,
            'timestamp' => $now->timestamp,
        ];
    }
}
