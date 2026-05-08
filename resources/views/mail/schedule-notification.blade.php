<x-mail::message>
# {{ $schedule->name }}

@if ($schedule->scheduled_at)
**{{ __('mail.schedule.starts_at') }}:** {{ $schedule->scheduled_at->isoFormat('LLLL') }}
@endif

@if ($schedule->completed_at)
**{{ __('mail.schedule.ends_at') }}:** {{ $schedule->completed_at->isoFormat('LLLL') }}
@endif

@if (!empty($schedule->message))
{!! \Illuminate\Support\Str::of($schedule->message)->markdown() !!}
@endif

---

<small>
[{{ __('mail.footer.manage') }}]({{ $manageUrl }}) — [{{ __('mail.footer.unsubscribe') }}]({{ $unsubscribeUrl }})

© {{ now()->year }} {{ config('app.name') }}. {{ __('mail.footer.rights') }}
</small>
</x-mail::message>
