<div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
    <div class="max-w-[75%]">
        <div class="rounded-lg px-4 py-3 {{ 
            $message['role'] === 'user' 
                ? 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100' 
                : ($message['role'] === 'system' 
                    ? 'bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800'
                    : 'bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 border border-gray-200 dark:border-gray-700') 
        }}">
            <div class="text-sm leading-relaxed prose prose-sm dark:prose-invert max-w-none">
                {!! nl2br(e($message['content'])) !!}
            </div>

            {{-- Progress Details (for assistant messages only) --}}
            @if($message['role'] === 'assistant' && !empty($message['progress']))
                <details class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                    <summary class="cursor-pointer text-xs hover:text-gray-700 dark:hover:text-gray-300 flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="font-bold text-gray-900 dark:text-gray-100">Detalhes do processamento</span>
                        <span class="text-gray-600 dark:text-gray-400">
                            ({{ count($message['progress']) }} passos)
                            @if(isset($message['total_time_ms']))
                                - {{ number_format($message['total_time_ms'], 0) }}ms
                            @endif
                        </span>
                    </summary>
                    <div class="mt-3 space-y-3">
                        @foreach($message['progress'] as $step)
                            <div class="border-l-2 {{ isset($step['metadata']['type']) && $step['metadata']['type'] === 'tool_complete' ? 'border-green-400 dark:border-green-600' : 'border-primary-200 dark:border-primary-800' }}">
                                <div class="pl-4 flex items-start gap-2">
                                    <svg class="w-3.5 h-3.5 text-primary-500 dark:text-primary-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <div class="flex-1">
                                        <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ $step['message'] }}</span>
                                        @if(isset($step['step_duration_ms']) && $step['step_duration_ms'] !== null)
                                            <span class="text-xs text-blue-600 dark:text-blue-400 ml-2 font-semibold">({{ $step['step_duration_ms'] }}ms)</span>
                                        @endif
                                        @if(isset($step['metadata']['tokens']))
                                            <span class="text-xs text-green-600 dark:text-green-400 ml-2 font-semibold">({{ $step['metadata']['tokens']['total'] }}tks)</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Tool Details --}}
                                @if(isset($step['metadata']['tool']))
                                    <details class="ml-4 mt-2">
                                        <summary class="cursor-pointer text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 pl-5 py-1">
                                            Ver detalhes técnicos
                                        </summary>
                                        <div class="mt-2 ml-5 space-y-2 pb-2">
                                            <div class="text-xs">
                                                <span class="font-semibold text-gray-600 dark:text-gray-400">Ferramenta:</span>
                                                <span class="text-gray-700 dark:text-gray-300 font-mono bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">{{ $step['metadata']['tool'] }}</span>
                                            </div>
                                            @if(isset($step['metadata']['metadata']['execution_time_ms']))
                                                <div class="text-xs">
                                                    <span class="font-semibold text-gray-600 dark:text-gray-400">Tempo:</span>
                                                    <span class="text-blue-600 dark:text-blue-400">{{ $step['metadata']['metadata']['execution_time_ms'] }}ms</span>
                                                </div>
                                            @endif
                                        </div>
                                    </details>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif
        </div>
    </div>
</div>
