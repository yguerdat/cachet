<x-mail::message>
# {{ __('mail.verify.heading') }}

{{ __('mail.verify.body') }}

<x-mail::button :url="$verifyUrl">
{{ __('mail.verify.button') }}
</x-mail::button>

{{ __('mail.verify.ignore') }}

— {{ config('app.name') }}
</x-mail::message>
