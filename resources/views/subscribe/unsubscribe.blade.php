<x-cachet::cachet :title="__('subscribe.unsubscribe.title')">
    <x-cachet::header />

    <main class="es-shell es-shell-narrow" style="max-width: 480px;">
        <h1 class="es-page-title">{{ __('subscribe.unsubscribe.title') }}</h1>
        <p class="es-page-intro">{{ __('subscribe.unsubscribe.intro') }}</p>

        <div class="es-form-card">
            <form method="POST" action="{{ route('subscribe.unsubscribe.destroy', $subscriber) }}">
                @csrf
                <button type="submit" class="es-btn-primary es-btn-danger" style="width: 100%; margin-bottom: 12px;">{{ __('subscribe.unsubscribe.confirm') }}</button>
                <a href="{{ route('subscribe.manage', $subscriber) }}" class="es-link-soft" style="display: block; text-align: center;">{{ __('subscribe.unsubscribe.cancel') }}</a>
            </form>
        </div>
    </main>

    <x-cachet::footer />
</x-cachet::cachet>
