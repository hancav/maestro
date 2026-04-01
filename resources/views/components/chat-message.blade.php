<div class="maestro-msg {{ $message['role'] === 'user' ? 'maestro-msg-user' : ($message['role'] === 'system' ? 'maestro-msg-system' : 'maestro-msg-assistant') }}">
    <div class="maestro-bubble {{ $message['role'] === 'user' ? 'maestro-bubble-user' : ($message['role'] === 'system' ? 'maestro-bubble-system' : 'maestro-bubble-assistant') }}">
        {{ $message['content'] }}
    </div>
</div>
