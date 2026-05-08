@php
    $statusColor = function ($enum): string {
        return match ($enum?->name) {
            'investigating' => '#eab308',
            'identified' => '#f97316',
            'watching' => '#3b82f6',
            'fixed' => '#10b981',
            default => '#71717a',
        };
    };

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

    $sortedUpdates = $incident->updates->sortByDesc('created_at')->values();
    $latestStatus = $sortedUpdates->first()?->status ?? $incident->status;
    $cardColor = $statusColor($latestStatus);
    $isResolved = $latestStatus?->name === 'fixed';
    $resolvedAt = $isResolved ? $sortedUpdates->first()?->created_at : null;
@endphp

<x-cachet::cachet :title="$incident->name">
    <x-cachet::header />

    <main class="es-shell" style="max-width: 880px;">

        <a href="{{ url(\Cachet\Cachet::path()) }}" class="es-link-soft" style="display: inline-flex; align-items: center; gap: 6px; margin-bottom: 20px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            Retour à la page de statut
        </a>

        <section class="es-hero" style="--es-hero-color: {{ $cardColor }}; --es-hero-color-soft: {{ $cardColor }}dd; --es-hero-color-shadow: {{ $cardColor }}88; padding: 32px;">
            <div class="es-hero-row">
                <div class="es-hero-icon @if(! $isResolved) is-active @endif">
                    @if ($isResolved)
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    @endif
                </div>
                <div style="flex: 1; min-width: 0;">
                    <span class="es-pill" style="background: rgba(255,255,255,0.25); color: #fff; backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.3); margin-bottom: 12px;">
                        {{ $latestStatus?->getLabel() ?? '—' }}
                    </span>
                    <h1 class="es-hero-title es-display" style="margin-top: 8px;">{{ $incident->name }}</h1>
                    <p class="es-hero-sub">
                        Ouvert {{ $incident->created_at?->diffForHumans() }}
                        @if ($isResolved && $resolvedAt)
                            · Résolu {{ $resolvedAt->diffForHumans() }}
                        @endif
                    </p>
                </div>
            </div>
        </section>

        @if ($incident->components->isNotEmpty())
            <section class="es-section" style="margin-top: 32px;">
                <h2 class="es-section-title es-display">Composants impactés</h2>
                <div class="es-incident-impact" style="margin: 0;">
                    @foreach ($incident->components as $c)
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

        <section class="es-section" style="margin-top: 32px;">
            <h2 class="es-section-title es-display">
                Historique de l'incident
                <span class="es-section-meta">{{ $sortedUpdates->count() ?: 1 }} {{ $sortedUpdates->count() > 1 ? 'mises à jour' : 'mise à jour' }}</span>
            </h2>

            <article class="es-card">
                <ol class="es-timeline">
                    @foreach ($sortedUpdates as $update)
                        <li class="es-timeline-item">
                            <span class="es-timeline-dot" style="--es-dot-color: {{ $statusColor($update->status) }};"></span>
                            <div class="es-timeline-head">
                                <span class="es-timeline-status">{{ $update->status?->getLabel() ?? '—' }}</span>
                                <span class="es-timeline-time" title="{{ $update->created_at?->isoFormat('LLLL') }}">{{ $update->created_at?->isoFormat('LLLL') }}</span>
                                @if ($loop->last)
                                    <span class="es-timeline-tag">panne d'origine</span>
                                @endif
                            </div>
                            @if (! empty($update->message))
                                <div class="es-timeline-body">{!! \App\Support\SafeMarkdown::convert($update->message) !!}</div>
                            @endif
                        </li>
                    @endforeach
                    @if ($sortedUpdates->isEmpty() && ! empty($incident->message))
                        <li class="es-timeline-item">
                            <span class="es-timeline-dot" style="--es-dot-color: {{ $cardColor }};"></span>
                            <div class="es-timeline-head">
                                <span class="es-timeline-status">{{ $incident->status?->getLabel() ?? '—' }}</span>
                                <span class="es-timeline-time" title="{{ $incident->created_at?->isoFormat('LLLL') }}">{{ $incident->created_at?->isoFormat('LLLL') }}</span>
                            </div>
                            <div class="es-timeline-body">{!! \App\Support\SafeMarkdown::convert($incident->message) !!}</div>
                        </li>
                    @endif
                </ol>
            </article>
        </section>

        <section class="es-section" style="margin-top: 32px; text-align: center;">
            <a href="{{ route('subscribe.create') }}" class="es-btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                Recevez les futures mises à jour par e-mail ou SMS
            </a>
        </section>
    </main>

    <x-cachet::footer />
</x-cachet::cachet>
