<x-filament::page>
    <div class="space-y-3">
        {{-- Header Bar --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 px-4 py-2.5">
            <div class="flex items-center gap-4">
                {{-- Stats --}}
                <div class="flex-shrink-0">
                    <div class="text-xs text-gray-500 dark:text-gray-500 flex items-center gap-3">
                        @if($totalInputTokens > 0 || $totalOutputTokens > 0)
                            <span class="inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                                </svg>
                                <span class="font-medium">{{ count($chatHistory) }}</span>
                            </span>
                            <span class="text-gray-400 dark:text-gray-600">•</span>
                            <span class="inline-flex items-center gap-1" title="Input Tokens">
                                <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                </svg>
                                <span class="font-medium">{{ number_format($totalInputTokens) }}</span>
                            </span>
                            <span class="text-gray-400 dark:text-gray-600">•</span>
                            <span class="inline-flex items-center gap-1" title="Output Tokens">
                                <svg class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                </svg>
                                <span class="font-medium">{{ number_format($totalOutputTokens) }}</span>
                            </span>
                        @endif
                    </div>
                </div>

                <div class="flex-1"></div>

                {{-- New Chat --}}
                <div class="flex-shrink-0">
                    <button wire:click="clearHistory"
                            type="button"
                            class="px-3 py-1.5 text-xs bg-primary-600 hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-600 text-white rounded-md transition-colors duration-150 font-medium shadow-sm">
                        Nova conversa
                    </button>
                </div>
            </div>
        </div>

        {{-- Chat Container --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 flex flex-col" style="height: calc(100vh - 280px); min-height: 500px; max-height: 800px;">
            {{-- Messages Area --}}
            <div class="flex-1 overflow-y-auto p-6" id="chat-messages">

                {{-- Empty State --}}
                @if(empty($chatHistory))
                    <div class="h-full flex items-center justify-center py-12"
                         wire:loading.remove wire:target="sendMessage">
                        <div class="max-w-4xl mx-auto text-center">
                            <div class="mb-12">
                                <div class="flex items-center justify-center gap-4">
                                    <div class="flex-shrink-0 w-16 h-16 rounded-full bg-primary-100 dark:bg-primary-900/20 flex items-center justify-center">
                                        <svg class="w-8 h-8 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                                        </svg>
                                    </div>
                                    <h2 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Maestro</h2>
                                </div>
                                <p class="mt-4 text-gray-500 dark:text-gray-400">Orquestrador multi-agente inteligente</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <button wire:click="askQuestion('Traduz o seguinte texto para inglês: Portugal é um país incrível com uma história rica.')"
                                    class="group p-6 text-left bg-white dark:bg-gray-800 hover:bg-primary-50 dark:hover:bg-primary-900/20 border-2 border-gray-200 dark:border-gray-600 hover:border-primary-500 rounded-xl transition-all duration-200 hover:shadow-xl hover:scale-[1.02]">
                                    <div class="flex items-start gap-4">
                                        <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
                                            <svg class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path>
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">Traduzir texto para inglês</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Testar o agente tradutor</p>
                                        </div>
                                    </div>
                                </button>

                                <button wire:click="askQuestion('Resume em 3 frases o que é inteligência artificial.')"
                                    class="group p-6 text-left bg-white dark:bg-gray-800 hover:bg-primary-50 dark:hover:bg-primary-900/20 border-2 border-gray-200 dark:border-gray-600 hover:border-primary-500 rounded-xl transition-all duration-200 hover:shadow-xl hover:scale-[1.02]">
                                    <div class="flex items-start gap-4">
                                        <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
                                            <svg class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">Resumir um tópico</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Testar o agente sumarizador</p>
                                        </div>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Chat messages --}}
                @if(!empty($chatHistory) || true)
                    <div class="space-y-6">
                        @foreach($chatHistory as $index => $message)
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

                                        @if($message['role'] === 'assistant' && !empty($message['progress']))
                                            <details class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                                                <summary class="cursor-pointer text-xs hover:text-gray-700 dark:hover:text-gray-300 flex items-center gap-2">
                                                    <svg class="w-3.5 h-3.5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    <span class="font-bold text-gray-900 dark:text-gray-100">Detalhes</span>
                                                    <span class="text-gray-600 dark:text-gray-400">
                                                        ({{ count($message['progress']) }} passos)
                                                        @if(isset($message['total_time_ms']))
                                                            - {{ number_format($message['total_time_ms'], 0) }}ms
                                                        @endif
                                                    </span>
                                                </summary>
                                                <div class="mt-3 space-y-2">
                                                    @foreach($message['progress'] as $step)
                                                        <div class="pl-4 border-l-2 border-primary-200 dark:border-primary-800 flex items-start gap-2">
                                                            <svg class="w-3.5 h-3.5 text-primary-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                            </svg>
                                                            <span class="text-xs text-gray-700 dark:text-gray-300">{{ $step['message'] }}</span>
                                                            @if(isset($step['step_duration_ms']) && $step['step_duration_ms'] !== null)
                                                                <span class="text-xs text-blue-600 dark:text-blue-400 font-semibold">({{ $step['step_duration_ms'] }}ms)</span>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </details>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        {{-- Streamed messages --}}
                        <div wire:stream="chat-messages-stream" class="space-y-6"></div>

                        {{-- Loading indicator --}}
                        <div wire:loading wire:target="sendMessage" class="flex justify-start">
                            <div class="max-w-[75%] w-full">
                                <div class="rounded-lg px-4 py-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-3">
                                            <span class="text-sm text-gray-600 dark:text-gray-400 thinking-animation">A pensar...</span>
                                        </div>
                                        <details open id="progress-details">
                                            <summary class="cursor-pointer text-xs text-gray-500 dark:text-gray-400 flex items-center gap-2">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <span>Progresso</span>
                                                <span wire:stream="progress-count" class="text-blue-600 dark:text-blue-400 font-semibold">(0 passos)</span>
                                            </summary>
                                            <div wire:stream="realtime-progress" class="mt-3 space-y-1.5 pl-6 border-l-2 border-primary-200 dark:border-primary-800"></div>
                                        </details>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Input --}}
            <div class="border-t border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800">
                <div class="flex items-start gap-3">
                    <textarea wire:model="userMessage" rows="1" id="user-message-input"
                        class="flex-1 resize-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 rounded-lg shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm"
                        placeholder="Escreva a sua mensagem..."
                        wire:loading.attr="disabled"></textarea>
                    <button type="button" wire:click="sendMessage"
                        class="p-2 bg-primary-600 hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-600 text-white rounded-lg transition-colors duration-150 disabled:opacity-50 inline-flex items-center justify-center shadow-sm"
                        wire:loading.attr="disabled" wire:loading.class="animate-pulse" wire:target="sendMessage">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('livewire:initialized', () => {
            function scrollToBottom() {
                const el = document.getElementById('chat-messages');
                if (el) el.scrollTop = el.scrollHeight;
            }

            Livewire.hook('morph.updated', () => {
                setTimeout(() => {
                    scrollToBottom();
                    const input = document.getElementById('user-message-input');
                    if (input && !input.disabled) input.focus();
                }, 150);
            });

            Livewire.hook('commit', ({ succeed }) => {
                succeed(() => {
                    setTimeout(() => {
                        scrollToBottom();
                        const input = document.getElementById('user-message-input');
                        if (input && !input.disabled) input.focus();
                    }, 200);
                });
            });

            const textarea = document.getElementById('user-message-input');
            if (textarea) {
                textarea.focus();
                textarea.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        Livewire.find(textarea.closest('[wire\\:id]').getAttribute('wire:id')).call('sendMessage');
                    }
                });
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = Math.min(this.scrollHeight, 200) + 'px';
                });
            }

            const observer = new MutationObserver(() => scrollToBottom());
            ['realtime-progress', 'chat-messages-stream'].forEach(name => {
                const el = document.querySelector(`[wire\\:stream="${name}"]`);
                if (el) observer.observe(el, { childList: true, subtree: true });
            });
        });
    </script>
    <style>
        @keyframes thinking { 0%, 100% { opacity: 0.3; } 50% { opacity: 1; } }
        .thinking-animation { animation: thinking 1s ease-in-out infinite; }
        @keyframes fade-in { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fade-in 0.3s ease-out; }
    </style>
    @endpush
</x-filament::page>
