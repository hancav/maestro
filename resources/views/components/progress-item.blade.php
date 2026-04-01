<div class="flex items-start gap-2 animate-fade-in">
    <svg class="w-3.5 h-3.5 text-primary-500 dark:text-primary-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
    </svg>
    <div class="flex-1 min-w-0">
        <span class="text-xs text-gray-600 dark:text-gray-400">{{ $message }}</span>
        @if(isset($step_duration_ms) && $step_duration_ms !== null)
            <span class="text-xs text-blue-600 dark:text-blue-400 ml-2 font-semibold">({{ $step_duration_ms }}ms)</span>
        @endif
        @if(isset($metadata['tokens']))
            <span class="text-xs text-green-600 dark:text-green-400 ml-2 font-semibold">({{ $metadata['tokens']['total'] }}tks)</span>
        @endif
    </div>
</div>
