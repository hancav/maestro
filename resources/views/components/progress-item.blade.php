<div class="maestro-progress-item">
    &#10003; {{ $message }}
    @if(isset($step_duration_ms) && $step_duration_ms !== null)
        <span class="time">{{ $step_duration_ms }}ms</span>
    @endif
</div>
