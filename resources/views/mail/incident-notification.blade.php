<x-mail::message>
# {{ $incident->name }}

@if ($displayStatus)
<p style="margin:0 0 16px;">
    <span style="display:inline-block;background:{{ $statusColor }};color:#ffffff;padding:4px 12px;border-radius:9999px;font-weight:600;font-size:13px;text-transform:uppercase;letter-spacing:0.05em;">
        {{ $displayStatus->getLabel() }}
    </span>
</p>
@endif

@if (!empty($displayMessage))
{!! \Illuminate\Support\Str::of($displayMessage)->markdown() !!}
@endif

<x-mail::button :url="$incidentUrl">
{{ __('mail.incident.button') }}
</x-mail::button>

---

<small>
[{{ __('mail.footer.manage') }}]({{ $manageUrl }}) — [{{ __('mail.footer.unsubscribe') }}]({{ $unsubscribeUrl }})

© {{ now()->year }} {{ config('app.name') }}. {{ __('mail.footer.rights') }}
</small>
</x-mail::message>
