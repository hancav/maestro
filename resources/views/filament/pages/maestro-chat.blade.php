<x-filament::page>
    <style>
        .maestro-chat-container { display: flex; flex-direction: column; height: calc(100vh - 200px); min-height: 400px; }
        .maestro-messages { flex: 1; overflow-y: auto; padding: 1.5rem; }
        .maestro-input-area { border-top: 1px solid var(--gray-200); padding: 1rem; display: flex; gap: 0.75rem; align-items: flex-start; }
        .maestro-input-area textarea { flex: 1; resize: none; border: 1px solid var(--gray-300); border-radius: 0.5rem; padding: 0.5rem 0.75rem; font-size: 0.875rem; font-family: inherit; outline: none; min-height: 40px; }
        .maestro-input-area textarea:focus { border-color: var(--primary-500); box-shadow: 0 0 0 1px var(--primary-500); }
        .maestro-send-btn { padding: 0.5rem 1rem; border-radius: 0.5rem; border: none; cursor: pointer; font-size: 0.875rem; font-weight: 500; color: white; background: var(--primary-600); }
        .maestro-send-btn:hover { background: var(--primary-700); }
        .maestro-send-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .maestro-msg { margin-bottom: 1rem; display: flex; }
        .maestro-msg-user { justify-content: flex-end; }
        .maestro-msg-assistant { justify-content: flex-start; }
        .maestro-msg-system { justify-content: center; }
        .maestro-bubble { max-width: 75%; padding: 0.75rem 1rem; border-radius: 0.75rem; font-size: 0.875rem; line-height: 1.6; white-space: pre-wrap; word-wrap: break-word; }
        .maestro-bubble-user { background: var(--primary-50); color: var(--gray-900); }
        .maestro-bubble-assistant { background: var(--gray-50); border: 1px solid var(--gray-200); color: var(--gray-900); }
        .maestro-bubble-system { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; font-size: 0.8rem; }
        .maestro-empty { display: flex; align-items: center; justify-content: center; height: 100%; text-align: center; }
        .maestro-empty h2 { font-size: 1.5rem; font-weight: 700; color: var(--gray-900); margin-bottom: 0.5rem; }
        .maestro-empty p { color: var(--gray-500); font-size: 0.875rem; margin-bottom: 2rem; }
        .maestro-examples { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; max-width: 600px; margin: 0 auto; }
        .maestro-example-btn { padding: 1rem; text-align: left; border: 1px solid var(--gray-200); border-radius: 0.75rem; background: white; cursor: pointer; font-size: 0.8rem; color: var(--gray-700); transition: border-color 0.15s; }
        .maestro-example-btn:hover { border-color: var(--primary-500); }
        .maestro-example-btn strong { display: block; margin-bottom: 0.25rem; color: var(--gray-900); }
        .maestro-header { display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 1rem; border-bottom: 1px solid var(--gray-200); font-size: 0.75rem; color: var(--gray-500); }
        .maestro-clear-btn { padding: 0.35rem 0.75rem; border-radius: 0.375rem; border: 1px solid var(--gray-300); background: white; cursor: pointer; font-size: 0.75rem; color: var(--gray-600); }
        .maestro-clear-btn:hover { background: var(--gray-50); }
        .maestro-progress { margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid var(--gray-200); font-size: 0.75rem; color: var(--gray-500); }
        .maestro-thinking { animation: maestro-pulse 1.5s ease-in-out infinite; font-size: 0.875rem; color: var(--gray-500); padding: 0.75rem 1rem; }
        @keyframes maestro-pulse { 0%, 100% { opacity: 0.4; } 50% { opacity: 1; } }
        .maestro-progress-item { padding: 0.15rem 0; font-size: 0.75rem; color: var(--gray-500); }
        .maestro-progress-item .time { color: var(--primary-600); font-weight: 600; margin-left: 0.5rem; }
    </style>

    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        {{-- Header --}}
        <div class="maestro-header">
            <div>
                @if($totalInputTokens > 0 || $totalOutputTokens > 0)
                    {{ count($chatHistory) }} msgs &middot;
                    {{ number_format($totalInputTokens) }} in &middot;
                    {{ number_format($totalOutputTokens) }} out
                @else
                    Maestro Orchestrator
                @endif
            </div>
            <button wire:click="clearHistory" class="maestro-clear-btn">Nova conversa</button>
        </div>

        <div class="maestro-chat-container">
            {{-- Messages --}}
            <div class="maestro-messages" id="chat-messages">

                @if(empty($chatHistory))
                    <div class="maestro-empty" wire:loading.remove wire:target="sendMessage">
                        <div>
                            <h2>Maestro</h2>
                            <p>Orquestrador multi-agente inteligente</p>
                            <div class="maestro-examples">
                                <button wire:click="askQuestion('Traduz para inglês: Portugal é um país com uma história rica.')" class="maestro-example-btn">
                                    <strong>Traduzir texto</strong>
                                    Testar o agente tradutor
                                </button>
                                <button wire:click="askQuestion('Resume em 3 frases o que é inteligência artificial.')" class="maestro-example-btn">
                                    <strong>Resumir um tópico</strong>
                                    Testar o agente sumarizador
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

                <div>
                    @foreach($chatHistory as $message)
                        <div class="maestro-msg {{ $message['role'] === 'user' ? 'maestro-msg-user' : ($message['role'] === 'system' ? 'maestro-msg-system' : 'maestro-msg-assistant') }}">
                            <div class="maestro-bubble {{ $message['role'] === 'user' ? 'maestro-bubble-user' : ($message['role'] === 'system' ? 'maestro-bubble-system' : 'maestro-bubble-assistant') }}">
                                {{ $message['content'] }}

                                @if($message['role'] === 'assistant' && !empty($message['progress']))
                                    <div class="maestro-progress">
                                        <details>
                                            <summary style="cursor:pointer;">
                                                {{ count($message['progress']) }} passos
                                                @if(isset($message['total_time_ms']))
                                                    &middot; {{ number_format($message['total_time_ms'], 0) }}ms
                                                @endif
                                            </summary>
                                            <div style="margin-top:0.25rem;">
                                                @foreach($message['progress'] as $step)
                                                    <div class="maestro-progress-item">
                                                        &#10003; {{ $step['message'] }}
                                                        @if(isset($step['step_duration_ms']) && $step['step_duration_ms'] !== null)
                                                            <span class="time">{{ $step['step_duration_ms'] }}ms</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </details>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    {{-- Streamed messages --}}
                    <div wire:stream="chat-messages-stream"></div>

                    {{-- Loading --}}
                    <div wire:loading wire:target="sendMessage">
                        <div class="maestro-msg maestro-msg-assistant">
                            <div class="maestro-bubble maestro-bubble-assistant">
                                <div class="maestro-thinking">A pensar...</div>
                                <div wire:stream="realtime-progress" style="margin-top:0.5rem;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Input --}}
            <div class="maestro-input-area">
                <textarea wire:model="userMessage"
                          rows="1"
                          id="user-message-input"
                          placeholder="Escreva a sua mensagem..."
                          wire:loading.attr="disabled"></textarea>
                <button wire:click="sendMessage"
                        class="maestro-send-btn"
                        wire:loading.attr="disabled"
                        wire:target="sendMessage">
                    Enviar
                </button>
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
                setTimeout(scrollToBottom, 100);
            });

            Livewire.hook('commit', ({ succeed }) => {
                succeed(() => setTimeout(() => {
                    scrollToBottom();
                    const input = document.getElementById('user-message-input');
                    if (input && !input.disabled) input.focus();
                }, 150));
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

            const observer = new MutationObserver(scrollToBottom);
            ['realtime-progress', 'chat-messages-stream'].forEach(name => {
                const el = document.querySelector(`[wire\\:stream="${name}"]`);
                if (el) observer.observe(el, { childList: true, subtree: true });
            });
        });
    </script>
    @endpush
</x-filament::page>
