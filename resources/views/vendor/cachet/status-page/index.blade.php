@php
    use Cachet\Enums\IncidentStatusEnum;

    $allComponents = collect();
    foreach ($componentGroups as $g) {
        foreach ($g->components as $c) $allComponents->push($c);
    }
    foreach ($ungroupedComponents as $c) $allComponents->push($c);
    $componentCount = $allComponents->count();

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

    $heroIcon = match ($overall['status']) {
        'ok' => 'check',
        'maintenance' => 'wrench',
        default => 'alert',
    };

    $formatMttr = function (?int $seconds): string {
        if ($seconds === null) return '—';
        if ($seconds < 60) return $seconds.' s';
        if ($seconds < 3600) return round($seconds / 60).' min';
        if ($seconds < 86400) return round($seconds / 3600, 1).' h';
        return round($seconds / 86400, 1).' j';
    };

    $sparklinePath = function (array $days, int $width = 200, int $height = 36): string {
        $scoreFor = fn (string $status): int => match ($status) {
            'ok' => 100, 'maintenance' => 70, 'degraded' => 65,
            'partial' => 35, 'major' => 5, default => 90,
        };
        $values = collect($days)->take(-30)->map(fn ($d) => $scoreFor($d['status']))->values()->all();
        if (empty($values)) return '';
        $count = count($values);
        $stepX = $count > 1 ? $width / ($count - 1) : 0;
        $points = [];
        foreach ($values as $i => $v) {
            $points[] = round($i * $stepX, 2).','.round($height - ($v / 100) * ($height - 4) - 2, 2);
        }
        return 'M '.implode(' L ', $points);
    };

    $sparklineFill = function (array $days, int $width = 200, int $height = 36): string {
        $scoreFor = fn (string $status): int => match ($status) {
            'ok' => 100, 'maintenance' => 70, 'degraded' => 65,
            'partial' => 35, 'major' => 5, default => 90,
        };
        $values = collect($days)->take(-30)->map(fn ($d) => $scoreFor($d['status']))->values()->all();
        if (empty($values)) return '';
        $count = count($values);
        $stepX = $count > 1 ? $width / ($count - 1) : 0;
        $points = ['0,'.$height];
        foreach ($values as $i => $v) {
            $points[] = round($i * $stepX, 2).','.round($height - ($v / 100) * ($height - 4) - 2, 2);
        }
        $points[] = $width.','.$height;
        return 'M '.implode(' L ', $points).' Z';
    };
@endphp

<x-cachet::cachet>
    <x-cachet::header />

    <main class="es-shell">

        <section class="es-hero" style="--es-hero-color: {{ $overall['color'] }}; --es-hero-color-soft: {{ $overall['color'] }}dd; --es-hero-color-shadow: {{ $overall['color'] }}88;">
            <div class="es-hero-row">
                <div class="es-hero-icon @if($overall['status'] !== 'ok') is-active @endif">
                    @if ($heroIcon === 'check')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    @elseif ($heroIcon === 'wrench')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    @endif
                </div>
                <div style="flex: 1; min-width: 0;">
                    <h1 class="es-hero-title es-display">{{ $overall['label'] }}</h1>
                    <p class="es-hero-sub">Dernière vérification {{ now()->diffForHumans() }} · {{ $componentCount }} composants surveillés</p>
                </div>
            </div>
            <div class="es-stats">
                <div class="es-stat-card">
                    <p class="es-stat-label">Uptime sur 90j</p>
                    <p class="es-stat-value">{{ number_format($stats['global_uptime'], 2, ',', ' ') }}<span class="es-stat-unit">%</span></p>
                </div>
                <div class="es-stat-card">
                    <p class="es-stat-label">Incidents actifs</p>
                    <p class="es-stat-value">{{ $stats['active_count'] }}</p>
                </div>
                <div class="es-stat-card">
                    <p class="es-stat-label">Résolus 30j</p>
                    <p class="es-stat-value">{{ $stats['resolved_30d'] }}</p>
                </div>
                <div class="es-stat-card">
                    <p class="es-stat-label">Tps moy. résolution</p>
                    <p class="es-stat-value">{{ $formatMttr($stats['mttr_seconds']) }}</p>
                </div>
            </div>
        </section>

        <div class="es-welcome">
            <p>Bienvenue sur la page d'état de santé des services eSéances.</p>
            <p>Vous trouverez ci-dessous des informations sur l'état de chaque produit et service eSéances, qu'il soit sous gestion d'Artionet ou d'un partenaire tiers (hébergeur). L'état des serveurs « on-premise » ne sont pas affichés de manière publique, mais n'hésitez pas à nous écrire en cas de besoin d'informations complémentaires.</p>
        </div>

        @if ($activeIncidents->isNotEmpty())
            <section class="es-section">
                <h2 class="es-section-title es-display">
                    Incidents en cours
                    <span class="es-section-meta">{{ $activeIncidents->count() }} {{ $activeIncidents->count() > 1 ? 'incidents' : 'incident' }}</span>
                </h2>
                @foreach ($activeIncidents as $incident)
                    @php
                        $sortedUpdates = $incident->updates->sortByDesc('created_at')->values();
                        $latestStatus = $sortedUpdates->first()?->status ?? $incident->status;
                        $cardColor = $statusColor($latestStatus);
                    @endphp
                    <article class="es-card es-incident-card" style="--es-card-color: {{ $cardColor }};">
                        <div class="es-incident-header">
                            <span class="es-pill" style="background: {{ $cardColor }};">{{ $latestStatus?->getLabel() ?? '—' }}</span>
                            <a class="es-incident-title es-display" href="{{ route('cachet.status-page.incident', $incident) }}">{{ $incident->name }}</a>
                        </div>

                        @if ($incident->components->isNotEmpty())
                            <div class="es-incident-impact">
                                @foreach ($incident->components as $c)
                                    @php $cd = $componentDot($c); $logo = $productIcon($c); @endphp
                                    <span class="es-impact-tag">
                                        @if ($logo)
                                            <img src="{{ asset($logo) }}" alt="">
                                        @else
                                            <span class="es-dot" style="background: {{ $cd['color'] }};"></span>
                                        @endif
                                        {{ $c->name }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        <ol class="es-timeline">
                            @foreach ($sortedUpdates as $update)
                                <li class="es-timeline-item">
                                    <span class="es-timeline-dot" style="--es-dot-color: {{ $statusColor($update->status) }};"></span>
                                    <div class="es-timeline-head">
                                        <span class="es-timeline-status">{{ $update->status?->getLabel() ?? '—' }}</span>
                                        <span class="es-timeline-time" title="{{ $update->created_at?->isoFormat('LLLL') }}">{{ $update->created_at?->diffForHumans() }}</span>
                                        @if ($loop->last)
                                            <span class="es-timeline-tag">panne d'origine</span>
                                        @endif
                                    </div>
                                    @if (! empty($update->message))
                                        <div class="es-timeline-body">{!! \Illuminate\Support\Str::of($update->message)->markdown() !!}</div>
                                    @endif
                                </li>
                            @endforeach
                            @if ($sortedUpdates->isEmpty() && ! empty($incident->message))
                                <li class="es-timeline-item">
                                    <span class="es-timeline-dot" style="--es-dot-color: {{ $cardColor }};"></span>
                                    <div class="es-timeline-head">
                                        <span class="es-timeline-status">{{ $incident->status?->getLabel() ?? '—' }}</span>
                                        <span class="es-timeline-time" title="{{ $incident->created_at?->isoFormat('LLLL') }}">{{ $incident->created_at?->diffForHumans() }}</span>
                                    </div>
                                    <div class="es-timeline-body">{!! \Illuminate\Support\Str::of($incident->message)->markdown() !!}</div>
                                </li>
                            @endif
                        </ol>
                    </article>
                @endforeach
            </section>
        @endif

        @if ($schedules->isNotEmpty())
            <section class="es-section">
                <h2 class="es-section-title es-display">
                    Maintenances planifiées
                    <span class="es-section-meta">{{ $schedules->count() }}</span>
                </h2>
                @foreach ($schedules as $schedule)
                    <article class="es-maint-card">
                        <a href="{{ route('status-page.schedule', $schedule) }}" class="es-maint-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            <span>{{ $schedule->name }}</span>
                        </a>
                        <div class="es-maint-meta">
                            @if ($schedule->scheduled_at)
                                <div><strong>Début :</strong> {{ $schedule->scheduled_at->isoFormat('LLLL') }}</div>
                            @endif
                            @if ($schedule->completed_at)
                                <div><strong>Fin :</strong> {{ $schedule->completed_at->isoFormat('LLLL') }}</div>
                            @endif
                        </div>
                        @if (! empty($schedule->message))
                            <div class="es-maint-body">{!! \Illuminate\Support\Str::of($schedule->message)->markdown() !!}</div>
                        @endif
                    </article>
                @endforeach
            </section>
        @endif

        <section class="es-section">
            <h2 class="es-section-title es-display">
                Composants
                <span class="es-section-meta">90 derniers jours</span>
            </h2>
            <div class="es-comp-grid">
                @foreach ($componentGroups as $group)
                    @if ($group->components->isNotEmpty())
                        @include('vendor.cachet.status-page.partials.group', [
                            'title' => $group->name,
                            'components' => $group->components,
                            'componentDot' => $componentDot,
                            'productIcon' => $productIcon,
                            'sparklinePath' => $sparklinePath,
                            'sparklineFill' => $sparklineFill,
                        ])
                    @endif
                @endforeach
                @if ($ungroupedComponents->isNotEmpty())
                    @include('vendor.cachet.status-page.partials.group', [
                        'title' => 'Autres composants',
                        'components' => $ungroupedComponents,
                        'componentDot' => $componentDot,
                        'productIcon' => $productIcon,
                        'sparklinePath' => $sparklinePath,
                        'sparklineFill' => $sparklineFill,
                    ])
                @endif
            </div>
        </section>

        @if ($pastIncidents->isNotEmpty())
            <section class="es-section">
                <h2 class="es-section-title es-display">Historique <span class="es-section-meta">30 derniers jours</span></h2>
                @foreach ($pastIncidents->groupBy(fn ($i) => $i->created_at->format('Y-m-d')) as $date => $items)
                    <div class="es-history-day">
                        <div class="es-history-day-label">{{ \Carbon\CarbonImmutable::parse($date)->isoFormat('dddd LL') }}</div>
                        <ul class="es-history-list">
                            @foreach ($items as $incident)
                                <li>
                                    <a class="es-history-item" href="{{ route('cachet.status-page.incident', $incident) }}">
                                        <span class="es-dot" style="background: #10b981;"></span>
                                        <span>{{ $incident->name }}</span>
                                        <span class="es-history-item-resolved">Résolu</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </section>
        @endif
    </main>

    <x-cachet::footer />
</x-cachet::cachet>
