<x-filament::page>
    <style>
        /* Container */
        .mc-wrap { display:flex; flex-direction:column; height:calc(100vh - 180px); min-height:400px; border-radius:0.75rem; overflow:hidden; border:1px solid rgba(128,128,128,0.15); }

        /* Header */
        .mc-head { display:flex; align-items:center; justify-content:space-between; padding:0.6rem 1rem; border-bottom:1px solid rgba(128,128,128,0.15); font-size:0.75rem; }
        .mc-head-label { opacity:0.5; }
        .mc-head-btn { padding:0.3rem 0.7rem; border-radius:0.375rem; border:1px solid rgba(128,128,128,0.25); background:transparent; cursor:pointer; font-size:0.7rem; opacity:0.7; }
        .mc-head-btn:hover { opacity:1; }

        /* Messages area */
        .mc-msgs { flex:1; overflow-y:auto; padding:1.5rem; }

        /* Empty state */
        .mc-empty { display:flex; align-items:center; justify-content:center; height:100%; text-align:center; }
        .mc-empty h2 { font-size:1.25rem; font-weight:600; margin-bottom:0.25rem; }
        .mc-empty p { opacity:0.45; font-size:0.8rem; margin-bottom:1.5rem; }
        .mc-examples { display:flex; gap:0.75rem; justify-content:center; flex-wrap:wrap; }
        .mc-ex-btn { padding:0.75rem 1rem; text-align:left; border:1px solid rgba(128,128,128,0.2); border-radius:0.5rem; background:transparent; cursor:pointer; font-size:0.78rem; max-width:240px; transition:border-color 0.15s; }
        .mc-ex-btn:hover { border-color:rgba(128,128,128,0.5); }
        .mc-ex-btn strong { display:block; margin-bottom:0.2rem; font-size:0.8rem; }
        .mc-ex-btn span { opacity:0.5; font-size:0.72rem; }

        /* Message bubbles */
        .mc-row { margin-bottom:0.75rem; display:flex; }
        .mc-row-u { justify-content:flex-end; }
        .mc-row-a { justify-content:flex-start; }
        .mc-row-s { justify-content:center; }
        .mc-bub { max-width:72%; padding:0.65rem 0.9rem; border-radius:0.6rem; font-size:0.85rem; line-height:1.55; white-space:pre-wrap; word-wrap:break-word; }
        .mc-bub-u { background:rgba(59,130,246,0.12); }
        .mc-bub-a { border:1px solid rgba(128,128,128,0.15); }
        .mc-bub-s { background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.2); color:#dc2626; font-size:0.78rem; }

        /* Progress inside bubble */
        .mc-prog { margin-top:0.5rem; padding-top:0.4rem; border-top:1px solid rgba(128,128,128,0.12); font-size:0.7rem; opacity:0.6; }
        .mc-prog summary { cursor:pointer; }
        .mc-prog-item { padding:0.1rem 0; }
        .mc-prog-item .t { color:rgba(59,130,246,0.8); font-weight:600; margin-left:0.4rem; }

        /* Thinking animation */
        .mc-think { animation:mc-pulse 1.5s ease-in-out infinite; font-size:0.82rem; opacity:0.5; padding:0.5rem 0; }
        @keyframes mc-pulse { 0%,100%{opacity:0.3} 50%{opacity:0.7} }

        /* Input area */
        .mc-input { display:flex; gap:0.6rem; align-items:flex-end; padding:0.75rem 1rem; border-top:1px solid rgba(128,128,128,0.15); }
        .mc-input textarea { flex:1; resize:none; border:1px solid rgba(128,128,128,0.25); border-radius:0.5rem; padding:0.5rem 0.7rem; font-size:0.85rem; font-family:inherit; outline:none; background:transparent; color:inherit; min-height:38px; }
        .mc-input textarea:focus { border-color:rgba(59,130,246,0.5); box-shadow:0 0 0 2px rgba(59,130,246,0.15); }
        .mc-input textarea::placeholder { opacity:0.4; }
        .mc-send { padding:0.45rem 1rem; border-radius:0.5rem; border:none; cursor:pointer; font-size:0.8rem; font-weight:500; color:#fff; background:#3b82f6; }
        .mc-send:hover { background:#2563eb; }
        .mc-send:disabled { opacity:0.4; cursor:not-allowed; }
    </style>

    <div class="mc-wrap">
        {{-- Header --}}
        <div class="mc-head">
            <span class="mc-head-label">
                @if($totalInputTokens > 0 || $totalOutputTokens > 0)
                    {{ count($chatHistory) }} msgs &middot; {{ number_format($totalInputTokens + $totalOutputTokens) }} tokens
                @else
                    Maestro
                @endif
            </span>
            <button wire:click="clearHistory" class="mc-head-btn">Nova conversa</button>
        </div>

        {{-- Messages --}}
        <div class="mc-msgs" id="chat-messages">
            @if(empty($chatHistory))
                <div class="mc-empty" wire:loading.remove wire:target="sendMessage">
                    <div>
                        <h2>Maestro</h2>
                        <p>Orquestrador multi-agente</p>
                        <div class="mc-examples">
                            <button wire:click="askQuestion('Traduz para inglês: Portugal é um país com uma história rica.')" class="mc-ex-btn">
                                <strong>Traduzir texto</strong>
                                <span>Testar o agente translator</span>
                            </button>
                            <button wire:click="askQuestion('Resume em 3 frases o que é inteligência artificial.')" class="mc-ex-btn">
                                <strong>Resumir tópico</strong>
                                <span>Testar o agente summarizer</span>
                            </button>
                            <button wire:click="askQuestion('Pesquisa sobre computação quântica e elabora um texto profissional detalhado.')" class="mc-ex-btn">
                                <strong>Pesquisar Tópico</strong>
                                <span>Testar o agente researcher</span>
                            </button>
                            <button wire:click="askQuestion('Cria um resumo sobre energias renováveis e gera um relatório em PDF.')" class="mc-ex-btn">
                                <strong>Gerar Relatório</strong>
                                <span>Testar o agente writer</span>
                            </button>
                            <button wire:click="askQuestion('Gera uma folha de cálculo com os resultados de vendas do mês passado.')" class="mc-ex-btn">
                                <strong>Gerar Spreadsheet</strong>
                                <span>Testar o agente spreadsheet</span>
                            </button>
                            <button wire:click="askQuestion('Faz um resumo sobre cibersegurança, escreve um relatório profissional e envia por email.')" class="mc-ex-btn">
                                <strong>Enviar Relatório</strong>
                                <span>Testar o agente mailer</span>
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            <div>
                @foreach($chatHistory as $message)
                    <div class="mc-row {{ $message['role'] === 'user' ? 'mc-row-u' : ($message['role'] === 'system' ? 'mc-row-s' : 'mc-row-a') }}">
                        <div class="mc-bub {{ $message['role'] === 'user' ? 'mc-bub-u' : ($message['role'] === 'system' ? 'mc-bub-s' : 'mc-bub-a') }}">{{ $message['content'] }}@if($message['role'] === 'assistant' && !empty($message['progress']))<div class="mc-prog"><details><summary>{{ count($message['progress']) }} passos @if(isset($message['total_time_ms']))&middot; {{ number_format($message['total_time_ms'], 0) }}ms @endif</summary>@foreach($message['progress'] as $step)<div class="mc-prog-item">✓ {{ $step['message'] }}@if(isset($step['step_duration_ms']) && $step['step_duration_ms'] !== null)<span class="t">{{ $step['step_duration_ms'] }}ms</span>@endif</div>@endforeach</details></div>@endif</div>
                    </div>
                @endforeach

                <div wire:stream="chat-messages-stream"></div>

                <div wire:loading wire:target="sendMessage">
                    <div class="mc-row mc-row-a">
                        <div class="mc-bub mc-bub-a">
                            <div class="mc-think">A pensar...</div>
                            <div wire:stream="realtime-progress"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Input --}}
        <div class="mc-input">
            <textarea wire:model="userMessage" rows="1" id="user-message-input" placeholder="Escreva a sua mensagem..." wire:loading.attr="disabled"></textarea>
            <button wire:click="sendMessage" class="mc-send" wire:loading.attr="disabled" wire:target="sendMessage">Enviar</button>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('livewire:initialized',()=>{
        function sb(){const e=document.getElementById('chat-messages');if(e)e.scrollTop=e.scrollHeight}
        Livewire.hook('morph.updated',()=>setTimeout(sb,100));
        Livewire.hook('commit',({succeed})=>{succeed(()=>setTimeout(()=>{sb();const i=document.getElementById('user-message-input');if(i&&!i.disabled)i.focus()},150))});
        const t=document.getElementById('user-message-input');
        if(t){t.focus();t.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();Livewire.find(t.closest('[wire\\:id]').getAttribute('wire:id')).call('sendMessage')}});t.addEventListener('input',function(){this.style.height='auto';this.style.height=Math.min(this.scrollHeight,200)+'px'})}
        const o=new MutationObserver(sb);['realtime-progress','chat-messages-stream'].forEach(n=>{const e=document.querySelector(`[wire\\:stream="${n}"]`);if(e)o.observe(e,{childList:true,subtree:true})});
    });
    </script>
    @endpush
</x-filament::page>
