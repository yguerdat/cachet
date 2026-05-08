<x-cachet::cachet :title="__('subscribe.manage.title')">
    <x-cachet::header />

    <div class="container mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold mb-2">{{ __('subscribe.manage.title') }}</h1>
        <p class="text-zinc-600 dark:text-zinc-400 mb-6">{{ __('subscribe.manage.intro') }}</p>

        @if (session('status'))
            <div class="mb-6 rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-700 dark:border-green-900 dark:bg-green-950 dark:text-green-300">
                {{ session('status') }}
            </div>
        @endif

        <dl class="mb-6 space-y-1 text-sm">
            @if ($subscriber->email)
                <div class="flex gap-2">
                    <dt class="font-medium">{{ __('subscribe.manage.email') }}:</dt>
                    <dd>{{ $subscriber->email }}
                        @if ($subscriber->verified_at)
                            <span class="text-green-600 dark:text-green-400">✓ {{ __('subscribe.manage.verified') }}</span>
                        @else
                            <span class="text-amber-600 dark:text-amber-400">{{ __('subscribe.manage.not_verified') }}</span>
                        @endif
                    </dd>
                </div>
            @endif
            @if ($subscriber->phone_number)
                <div class="flex gap-2">
                    <dt class="font-medium">{{ __('subscribe.manage.phone') }}:</dt>
                    <dd>{{ $subscriber->phone_number }}
                        @if ($subscriber->phone_verified_at)
                            <span class="text-green-600 dark:text-green-400">✓ {{ __('subscribe.manage.verified') }}</span>
                        @else
                            <span class="text-amber-600 dark:text-amber-400">{{ __('subscribe.manage.not_verified') }}</span>
                        @endif
                    </dd>
                </div>
            @endif
        </dl>

        <form method="POST" action="{{ route('subscribe.manage.update', $subscriber) }}" class="space-y-6">
            @csrf

            <fieldset>
                <legend class="text-sm font-medium mb-2">{{ __('subscribe.field.scope') }}</legend>

                <label class="flex items-center gap-2 mb-3">
                    <input type="checkbox" name="global" value="1" {{ $subscriber->global ? 'checked' : '' }}>
                    <span>{{ __('subscribe.field.global') }}</span>
                </label>

                <div class="mt-4 space-y-4">
                    @foreach ($componentGroups as $group)
                        @if ($group->components->isNotEmpty())
                            <div class="rounded-md border border-zinc-200 dark:border-zinc-800">
                                <div class="border-b border-zinc-200 bg-zinc-50 px-3 py-2 text-sm font-semibold dark:border-zinc-800 dark:bg-zinc-900/40">
                                    {{ $group->name }}
                                </div>
                                <div class="grid grid-cols-1 gap-2 p-3 sm:grid-cols-2">
                                    @foreach ($group->components as $component)
                                        <label class="flex items-center gap-2">
                                            <input type="checkbox" name="components[]" value="{{ $component->id }}"
                                                @checked(in_array($component->id, $subscribedComponentIds, true))>
                                            <span>{{ $component->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach

                    @if ($ungroupedComponents->isNotEmpty())
                        <div class="rounded-md border border-zinc-200 dark:border-zinc-800">
                            <div class="border-b border-zinc-200 bg-zinc-50 px-3 py-2 text-sm font-semibold dark:border-zinc-800 dark:bg-zinc-900/40">
                                {{ __('subscribe.field.other_components') }}
                            </div>
                            <div class="grid grid-cols-1 gap-2 p-3 sm:grid-cols-2">
                                @foreach ($ungroupedComponents as $component)
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="components[]" value="{{ $component->id }}"
                                            @checked(in_array($component->id, $subscribedComponentIds, true))>
                                        <span>{{ $component->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </fieldset>

            <div class="flex items-center justify-between pt-2">
                <button type="submit"
                    class="inline-flex justify-center rounded-md bg-accent px-4 py-2 text-sm font-semibold text-accent-foreground shadow-sm ring-1 ring-accent/30 transition hover:opacity-90">
                    {{ __('subscribe.manage.save') }}
                </button>
                <a href="{{ route('subscribe.unsubscribe', $subscriber) }}"
                    class="text-sm text-red-600 hover:text-red-700 dark:text-red-400">{{ __('subscribe.manage.unsubscribe_link') }}</a>
            </div>
        </form>
    </div>
</x-cachet::cachet>
