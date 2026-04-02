<div class="mc-row {{ $message['role'] === 'user' ? 'mc-row-u' : ($message['role'] === 'system' ? 'mc-row-s' : 'mc-row-a') }}">
    <div class="mc-bub {{ $message['role'] === 'user' ? 'mc-bub-u' : ($message['role'] === 'system' ? 'mc-bub-s' : 'mc-bub-a') }}">{{ $message['content'] }}</div>
</div>
