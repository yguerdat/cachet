@php
    $componentDot = function ($component): array {
        return match ($component->status?->name) {
            'operational' => ['color' => '#10b981', 'label' => 'Opérationnel'],
            'performance_issues' => ['color' => '#eab308', 'label' => 'Lenteurs'],
            'partial_outage' => ['color' => '#f97316', 'label' => 'Panne partielle'],
            'major_outage' => ['color' => '#ef4444', 'label' => 'Panne majeure'],
            'under_maintenance' => ['color' => '#3b82f6', 'label' => 'Maintenance'],
            default => ['color' => '#a1a1aa', 'label' => 'État inconnu'],
        };
    };

    $productIcon = function ($component): ?string {
        $name = mb_strtolower($component->name);
        if (str_contains($name, 'executive')) return '/vendor/eseances/products/executive.svg';
        if (str_contains($name, 'legislative')) return '/vendor/eseances/products/legislative.svg';
        if (str_contains($name, 'universe')) return '/vendor/eseances/products/universe.svg';
        if (str_contains($name, 'flow')) return '/vendor/eseances/products/flow.png';
        return null;
    };

    $sortedUpdates = $schedule->updates->sortByDesc('created_at')->values();
    $now = now();
    $isFuture = $schedule->scheduled_at && $schedule->scheduled_at->isAfter($now);
    $isOngoing = $schedule->scheduled_at && $schedule->scheduled_at->isBefore($now)
        && (! $schedule->completed_at || $schedule->completed_at->isAfter($now));
    $isCompleted = $schedule->completed_at && $schedule->completed_at->isBefore($now);

    $stateLabel = $isCompleted ? 'Terminée' : ($isOngoing ? 'En cours' : ($isFuture ? 'Planifiée' : 'Maintenance'));
    $heroColor = '#3b82f6'; // blue accent for maintenance
@endphp

<x-cachet::cachet :title="$schedule->name">
    <x-cachet::header />

    <main class="es-shell" style="max-width: 880px;">

        <a href="{{ url(\Cachet\Cachet::path()) }}" class="es-link-soft" style="display: inline-flex; align-items: center; gap: 6px; margin-bottom: 20px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            Retour à la page de statut
        </a>

        <section class="es-hero" style="--es-hero-color: {{ $heroColor }}; --es-hero-color-soft: {{ $heroColor }}dd; --es-hero-color-shadow: {{ $heroColor }}88;">
            <div class="es-hero-row">
                <div class="es-hero-icon @if($isOngoing) is-active @endif">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <span class="es-pill" style="background: rgba(255,255,255,0.25); color: #fff; backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.3); margin-bottom: 12px;">
                        {{ $stateLabel }}
                    </span>
                    <h1 class="es-hero-title es-display" style="margin-top: 8px;">{{ $schedule->name }}</h1>
                    <p class="es-hero-sub">
                        @if ($schedule->scheduled_at)
                            Début : {{ $schedule->scheduled_at->isoFormat('LLLL') }}
                        @endif
                        @if ($schedule->completed_at)
                            · Fin : {{ $schedule->completed_at->isoFormat('LLLL') }}
                        @endif
                    </p>
                </div>
            </div>
        </section>

        @if ($schedule->components->isNotEmpty())
            <section class="es-section" style="margin-top: 32px;">
                <h2 class="es-section-title es-display">Composants concernés</h2>
                <div class="es-incident-impact" style="margin: 0;">
                    @foreach ($schedule->components as $c)
                        @php $cd = $componentDot($c); $logo = $productIcon($c); @endphp
                        <span class="es-impact-tag" style="font-size: 14px; padding: 8px 14px;">
                            @if ($logo)
                                <img src="{{ asset($logo) }}" alt="" style="width: 20px; height: 20px;">
                            @else
                                <span class="es-dot" style="background: {{ $cd['color'] }};"></span>
                            @endif
                            {{ $c->name }}
                        </span>
                    @endforeach
                </div>
            </section>
        @endif

        @if (! empty($schedule->message))
            <section class="es-section" style="margin-top: 32px;">
                <h2 class="es-section-title es-display">Description</h2>
                <article class="es-card">
                    <div class="es-timeline-body">{!! \App\Support\SafeMarkdown::convert($schedule->message) !!}</div>
                </article>
            </section>
        @endif

        @if ($sortedUpdates->isNotEmpty())
            <section class="es-section" style="margin-top: 32px;">
                <h2 class="es-section-title es-display">
                    Mises à jour
                    <span class="es-section-meta">{{ $sortedUpdates->count() }}</span>
                </h2>
                <article class="es-card">
                    <ol class="es-timeline">
                        @foreach ($sortedUpdates as $update)
                            <li class="es-timeline-item">
                                <span class="es-timeline-dot" style="--es-dot-color: {{ $heroColor }};"></span>
                                <div class="es-timeline-head">
                                    <span class="es-timeline-time" title="{{ $update->created_at?->isoFormat('LLLL') }}">{{ $update->created_at?->isoFormat('LLLL') }}</span>
                                </div>
                                @if (! empty($update->message))
                                    <div class="es-timeline-body">{!! \App\Support\SafeMarkdown::convert($update->message) !!}</div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </article>
            </section>
        @endif

        <section class="es-section" style="margin-top: 32px; text-align: center;">
            <a href="{{ route('subscribe.create') }}" class="es-btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                Recevez les futures mises à jour par e-mail ou SMS
            </a>
        </section>
    </main>

    <x-cachet::footer />
</x-cachet::cachet>
