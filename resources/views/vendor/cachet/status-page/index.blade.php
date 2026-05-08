@php
    use Cachet\Enums\IncidentStatusEnum;

    $statusBadgeColor = function ($enum): string {
        return match ($enum?->name) {
            'investigating' => '#eab308',
            'identified' => '#f97316',
            'watching' => '#3b82f6',
            'fixed' => '#22c55e',
            default => '#a1a1aa',
        };
    };

    $componentDot = function ($component): array {
        $status = $component->status ?? null;
        return match ($status?->name) {
            'operational' => ['color' => '#22c55e', 'label' => 'Opérationnel'],
            'performance_issues' => ['color' => '#eab308', 'label' => 'Lenteurs'],
            'partial_outage' => ['color' => '#f97316', 'label' => 'Panne partielle'],
            'major_outage' => ['color' => '#ef4444', 'label' => 'Panne majeure'],
            'under_maintenance' => ['color' => '#3b82f6', 'label' => 'Maintenance'],
            default => ['color' => '#a1a1aa', 'label' => 'État inconnu'],
        };
    };
@endphp

<x-cachet::cachet>
    <x-cachet::header />

    <style>
        @keyframes es-pulse {
            0%, 100% { box-shadow: 0 0 0 0 currentColor; opacity: 1; }
            50% { box-shadow: 0 0 0 6px transparent; opacity: 0.7; }
        }
        .es-pulse { animation: es-pulse 2s infinite; }
        .es-bar { transition: transform 120ms ease-out; }
        .es-bar:hover { transform: scaleY(1.5); }
    </style>

    <main class="container mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 space-y-10">

        {{-- ───────── Hero overall status ───────── --}}
        <section>
            <div class="flex items-center gap-4 rounded-2xl border p-6"
                 style="background: {{ $overall['accent'] }}; border-color: {{ $overall['color'] }}33;">
                <span class="relative inline-flex h-3 w-3 rounded-full"
                      style="background: {{ $overall['color'] }}; color: {{ $overall['color'] }};"
                      @class(['es-pulse' => $overall['status'] !== 'ok'])></span>
                <div class="flex-1">
                    <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ $overall['label'] }}
                    </h1>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        Dernière vérification {{ now()->diffForHumans() }}
                    </p>
                </div>
            </div>
        </section>

        {{-- ───────── Active incidents ───────── --}}
        @if ($activeIncidents->isNotEmpty())
            <section>
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                    Incidents en cours · {{ $activeIncidents->count() }}
                </h2>
                <div class="space-y-4">
                    @foreach ($activeIncidents as $incident)
                        @php
                            $sortedUpdates = $incident->updates->sortByDesc('created_at')->values();
                            $latestStatus = $sortedUpdates->first()?->status ?? $incident->status;
                            $latestColor = $statusBadgeColor($latestStatus);
                        @endphp
                        <article class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/40">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-white"
                                      style="background: {{ $latestColor }};">
                                    {{ $latestStatus?->getLabel() ?? '—' }}
                                </span>
                                <a href="{{ route('cachet.status-page.incident', $incident) }}"
                                   class="font-semibold text-zinc-900 hover:underline dark:text-zinc-100">
                                    {{ $incident->name }}
                                </a>
                            </div>

                            @if ($incident->components->isNotEmpty())
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    Impact :
                                    @foreach ($incident->components as $c)
                                        <span class="inline-flex items-center gap-1">
                                            <span class="inline-block h-1.5 w-1.5 rounded-full"
                                                  style="background: {{ $componentDot($c)['color'] }};"></span>
                                            {{ $c->name }}@if (! $loop->last),@endif
                                        </span>
                                    @endforeach
                                </p>
                            @endif

                            <ol class="mt-5 space-y-4 border-l border-zinc-200 pl-5 dark:border-zinc-800">
                                @foreach ($sortedUpdates as $update)
                                    @php $isOriginal = $loop->last; @endphp
                                    <li class="relative">
                                        <span class="absolute -left-[27px] mt-1.5 inline-flex h-3 w-3 rounded-full ring-4 ring-white dark:ring-zinc-900"
                                              style="background: {{ $statusBadgeColor($update->status) }};"></span>
                                        <div class="flex flex-wrap items-baseline gap-2">
                                            <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                                {{ $update->status?->getLabel() ?? '—' }}
                                            </span>
                                            <time class="text-xs text-zinc-500 dark:text-zinc-500"
                                                  title="{{ $update->created_at?->isoFormat('LLLL') }}">
                                                {{ $update->created_at?->diffForHumans() }}
                                            </time>
                                            @if ($isOriginal)
                                                <span class="rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                                    panne d'origine
                                                </span>
                                            @endif
                                        </div>
                                        @if (! empty($update->message))
                                            <div class="prose prose-sm mt-1 max-w-none text-zinc-700 dark:prose-invert dark:text-zinc-300">
                                                {!! \Illuminate\Support\Str::of($update->message)->markdown() !!}
                                            </div>
                                        @endif
                                    </li>
                                @endforeach

                                @if ($sortedUpdates->isEmpty() && ! empty($incident->message))
                                    <li class="relative">
                                        <span class="absolute -left-[27px] mt-1.5 inline-flex h-3 w-3 rounded-full ring-4 ring-white dark:ring-zinc-900"
                                              style="background: {{ $statusBadgeColor($incident->status) }};"></span>
                                        <div class="flex flex-wrap items-baseline gap-2">
                                            <span class="text-sm font-semibold">{{ $incident->status?->getLabel() ?? '—' }}</span>
                                            <time class="text-xs text-zinc-500"
                                                  title="{{ $incident->created_at?->isoFormat('LLLL') }}">
                                                {{ $incident->created_at?->diffForHumans() }}
                                            </time>
                                        </div>
                                        <div class="prose prose-sm mt-1 max-w-none text-zinc-700 dark:prose-invert dark:text-zinc-300">
                                            {!! \Illuminate\Support\Str::of($incident->message)->markdown() !!}
                                        </div>
                                    </li>
                                @endif
                            </ol>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ───────── Active maintenance ───────── --}}
        @if ($schedules->isNotEmpty())
            <section>
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                    Maintenances planifiées · {{ $schedules->count() }}
                </h2>
                <div class="space-y-4">
                    @foreach ($schedules as $schedule)
                        <article class="rounded-xl border border-blue-200 bg-blue-50/40 p-5 shadow-sm dark:border-blue-900 dark:bg-blue-950/20">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-2 w-2 rounded-full bg-blue-500"></span>
                                <h3 class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $schedule->name }}</h3>
                            </div>
                            <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                                @if ($schedule->scheduled_at)
                                    <div class="flex gap-2">
                                        <dt class="font-medium text-zinc-600 dark:text-zinc-400">Début :</dt>
                                        <dd class="text-zinc-900 dark:text-zinc-100">{{ $schedule->scheduled_at->isoFormat('LLLL') }}</dd>
                                    </div>
                                @endif
                                @if ($schedule->completed_at)
                                    <div class="flex gap-2">
                                        <dt class="font-medium text-zinc-600 dark:text-zinc-400">Fin :</dt>
                                        <dd class="text-zinc-900 dark:text-zinc-100">{{ $schedule->completed_at->isoFormat('LLLL') }}</dd>
                                    </div>
                                @endif
                            </dl>
                            @if (! empty($schedule->message))
                                <div class="prose prose-sm mt-3 max-w-none dark:prose-invert">
                                    {!! \Illuminate\Support\Str::of($schedule->message)->markdown() !!}
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ───────── Components with 90-day uptime ───────── --}}
        <section>
            <div class="mb-4 flex items-end justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                    Composants
                </h2>
                <span class="text-xs text-zinc-500 dark:text-zinc-500">90 derniers jours</span>
            </div>

            <div class="space-y-6">
                @foreach ($componentGroups as $group)
                    @if ($group->components->isNotEmpty())
                        @include('vendor.cachet.status-page.partials.group', [
                            'title' => $group->name,
                            'components' => $group->components,
                        ])
                    @endif
                @endforeach

                @if ($ungroupedComponents->isNotEmpty())
                    @include('vendor.cachet.status-page.partials.group', [
                        'title' => 'Autres composants',
                        'components' => $ungroupedComponents,
                    ])
                @endif
            </div>
        </section>

        {{-- ───────── Past incidents history ───────── --}}
        @if ($pastIncidents->isNotEmpty())
            <section>
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                    Historique · 30 derniers jours
                </h2>
                <div class="space-y-4">
                    @foreach ($pastIncidents->groupBy(fn ($i) => $i->created_at->format('Y-m-d')) as $date => $items)
                        <div>
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-500">
                                {{ \Carbon\CarbonImmutable::parse($date)->isoFormat('LL') }}
                            </h3>
                            <ul class="space-y-2">
                                @foreach ($items as $incident)
                                    <li>
                                        <a href="{{ route('cachet.status-page.incident', $incident) }}"
                                           class="flex items-center gap-3 rounded-lg border border-zinc-200 bg-white px-4 py-3 text-sm hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900/40 dark:hover:bg-zinc-900/70">
                                            <span class="inline-flex h-2 w-2 rounded-full bg-green-500"></span>
                                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $incident->name }}</span>
                                            <span class="ml-auto text-xs text-zinc-500">Résolu</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

    </main>

    <x-cachet::footer />
</x-cachet::cachet>
