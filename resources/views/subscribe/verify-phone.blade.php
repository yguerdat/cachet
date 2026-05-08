<x-cachet::cachet :title="__('subscribe.verify_phone.title')">
    <div class="container mx-auto max-w-md px-4 py-10 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold mb-2">{{ __('subscribe.verify_phone.title') }}</h1>
        <p class="text-zinc-600 dark:text-zinc-400 mb-6">
            {{ __('subscribe.verify_phone.intro', ['phone' => $subscriber->phone_number]) }}
        </p>

        @if ($errors->any())
            <div class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                @foreach ($errors->all() as $error)
                    {{ $error }}
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('subscribe.verify-phone.confirm', $subscriber) }}" class="space-y-4">
            @csrf
            <div>
                <label for="code" class="block text-sm font-medium mb-1">{{ __('subscribe.verify_phone.code') }}</label>
                <input type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" name="code" id="code" required
                    class="block w-full rounded-md border-zinc-300 text-center text-2xl tracking-widest font-mono focus:border-zinc-500 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900">
            </div>
            <button type="submit"
                class="w-full inline-flex justify-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                {{ __('subscribe.verify_phone.submit') }}
            </button>
        </form>
    </div>
</x-cachet::cachet>
