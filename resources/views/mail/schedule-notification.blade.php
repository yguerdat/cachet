<x-mail::message>
# {{ $schedule->name }}

@if ($schedule->scheduled_at)
**{{ __('mail.schedule.starts_at') }}:** {{ $schedule->scheduled_at->isoFormat('LLLL') }}
@endif

@if ($schedule->completed_at)
**{{ __('mail.schedule.ends_at') }}:** {{ $schedule->completed_at->isoFormat('LLLL') }}
@endif

@if (!empty($schedule->message))
{!! \App\Support\SafeMarkdown::convert($schedule->message) !!}
@endif

<x-mail::button :url="$scheduleUrl">
Voir le détail de la maintenance
</x-mail::button>

---

[{{ __('mail.footer.manage') }}]({{ $manageUrl }}) · [{{ __('mail.footer.unsubscribe') }}]({{ $unsubscribeUrl }})
</x-mail::message>
