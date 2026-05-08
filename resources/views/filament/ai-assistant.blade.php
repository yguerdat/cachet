@php
    $aiConfig = [
        'endpoints' => [
            'incident' => route('admin.ai.incident'),
            'incidentUpdate' => route('admin.ai.incident-update'),
            'maintenance' => route('admin.ai.maintenance'),
        ],
        'csrf' => csrf_token(),
        'enabled' => filled(config('anthropic.api_key')),
    ];
@endphp

@if ($aiConfig['enabled'])
<script>
    window.eseancesAi = @json($aiConfig);
</script>
<script src="{{ asset('vendor/eseances/ai-assistant.js') }}?v=1" defer></script>
@endif
