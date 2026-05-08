<x-cachet::cachet :title="__('subscribe.verify_phone.title')">
    <x-cachet::header />

    <main class="es-shell es-shell-narrow" style="max-width: 480px;">
        <h1 class="es-page-title">{{ __('subscribe.verify_phone.title') }}</h1>
        <p class="es-page-intro">{{ __('subscribe.verify_phone.intro', ['phone' => $subscriber->phone_number]) }}</p>

        @if ($errors->any())
            <div class="es-flash es-flash-error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="es-form-card">
            <form method="POST" action="{{ route('subscribe.verify-phone.confirm', $subscriber) }}">
                @csrf
                <div class="es-field">
                    <label for="code" class="es-label">{{ __('subscribe.verify_phone.code') }}</label>
                    <input type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" name="code" id="code" required class="es-input es-input-code" autofocus>
                </div>
                <button type="submit" class="es-btn-primary" style="width: 100%;">{{ __('subscribe.verify_phone.submit') }}</button>
            </form>
        </div>
    </main>

    <x-cachet::footer />
</x-cachet::cachet>
