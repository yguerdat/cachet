<div class="border-b border-zinc-900/10 bg-zinc-50 dark:border-white/15 dark:bg-zinc-900/70">
    <div class="container mx-auto flex max-w-5xl flex-col items-center justify-between gap-2 px-4 py-2.5 text-sm sm:flex-row sm:px-6 lg:px-8">
        <span class="text-zinc-700 dark:text-zinc-300">
            {{ __('subscribe.banner.text') }}
        </span>
        <a href="{{ route('subscribe.create') }}"
           class="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-sm font-semibold text-accent-foreground shadow-sm ring-1 ring-accent/30 transition hover:opacity-90">
            {{ __('subscribe.banner.cta') }}
        </a>
    </div>
</div>
