<x-cachet::cachet :title="__('subscribe.title')">
    <div class="container mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold mb-2">{{ __('subscribe.title') }}</h1>
        <p class="text-zinc-600 dark:text-zinc-400 mb-6">{{ __('subscribe.intro') }}</p>

        @if (session('status'))
            <div class="mb-6 rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-700 dark:border-green-900 dark:bg-green-950 dark:text-green-300">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('subscribe.store') }}" class="space-y-6">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium mb-1">{{ __('subscribe.field.email') }}</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}"
                    class="block w-full rounded-md border-zinc-300 shadow-sm focus:border-zinc-500 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900"
                    placeholder="vous@exemple.com">
            </div>

            <div>
                <label for="phone_number" class="block text-sm font-medium mb-1">{{ __('subscribe.field.phone') }}</label>
                <input type="tel" name="phone_number" id="phone_number" value="{{ old('phone_number') }}"
                    class="block w-full rounded-md border-zinc-300 shadow-sm focus:border-zinc-500 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900"
                    placeholder="+41791234567">
                <p class="mt-1 text-xs text-zinc-500">{{ __('subscribe.field.phone_help') }}</p>
            </div>

            <fieldset>
                <legend class="text-sm font-medium mb-2">{{ __('subscribe.field.scope') }}</legend>

                <label class="flex items-center gap-2 mb-3">
                    <input type="checkbox" name="global" value="1" {{ old('global', true) ? 'checked' : '' }}>
                    <span>{{ __('subscribe.field.global') }}</span>
                </label>

                <details class="mt-2">
                    <summary class="cursor-pointer text-sm text-zinc-600 dark:text-zinc-400">{{ __('subscribe.field.choose_components') }}</summary>
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach ($components as $component)
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="components[]" value="{{ $component->id }}"
                                    @checked(in_array($component->id, old('components', []), true))>
                                <span>{{ $component->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </details>
            </fieldset>

            <div class="pt-2">
                <button type="submit"
                    class="inline-flex justify-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                    {{ __('subscribe.action.submit') }}
                </button>
            </div>
        </form>
    </div>
</x-cachet::cachet>
