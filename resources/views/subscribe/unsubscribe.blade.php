<x-cachet::cachet :title="__('subscribe.unsubscribe.title')">
    <div class="container mx-auto max-w-md px-4 py-10 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold mb-2">{{ __('subscribe.unsubscribe.title') }}</h1>
        <p class="text-zinc-600 dark:text-zinc-400 mb-6">{{ __('subscribe.unsubscribe.intro') }}</p>

        <form method="POST" action="{{ route('subscribe.unsubscribe.destroy', $subscriber) }}" class="space-y-4">
            @csrf
            <button type="submit"
                class="w-full inline-flex justify-center rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                {{ __('subscribe.unsubscribe.confirm') }}
            </button>
            <a href="{{ route('subscribe.manage', $subscriber) }}"
                class="block text-center text-sm text-zinc-600 hover:text-zinc-800 dark:text-zinc-400">{{ __('subscribe.unsubscribe.cancel') }}</a>
        </form>
    </div>
</x-cachet::cachet>
