<x-mail::message>
# {{ $incident->name }}

**{{ __('mail.incident.status') }}:** {{ $incident->status?->getLabel() ?? '' }}

@if (!empty($incident->message))
{!! $incident->message !!}
@endif

<x-mail::button :url="$incidentUrl">
{{ __('mail.incident.button') }}
</x-mail::button>

---

<small>
{{ __('mail.footer.preferences') }} [{{ __('mail.footer.manage') }}]({{ $manageUrl }}) — [{{ __('mail.footer.unsubscribe') }}]({{ $unsubscribeUrl }})
</small>
</x-mail::message>
