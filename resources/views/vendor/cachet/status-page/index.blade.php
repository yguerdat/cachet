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
            'fixed' => '#22c55e',
            default => '#a1a1aa',
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
            'ok' => 100,
            'maintenance' => 70,
            'degraded' => 65,
            'partial' => 35,
            'major' => 5,
            default => 90,
        };
        $values = collect($days)->take(-30)->map(fn ($d) => $scoreFor($d['status']))->values()->all();
        if (empty($values)) return '';
        $count = count($values);
        $stepX = $count > 1 ? $width / ($count - 1) : 0;
        $points = [];
        foreach ($values as $i => $v) {
            $x = round($i * $stepX, 2);
            $y = round($height - ($v / 100) * ($height - 4) - 2, 2);
            $points[] = $x.','.$y;
        }
        return 'M '.implode(' L ', $points);
    };

    $sparklineFill = function (array $days, int $width = 200, int $height = 36): string {
        $scoreFor = fn (string $status): int => match ($status) {
            'ok' => 100,
            'maintenance' => 70,
            'degraded' => 65,
            'partial' => 35,
            'major' => 5,
            default => 90,
        };
        $values = collect($days)->take(-30)->map(fn ($d) => $scoreFor($d['status']))->values()->all();
        if (empty($values)) return '';
        $count = count($values);
        $stepX = $count > 1 ? $width / ($count - 1) : 0;
        $points = ['0,'.$height];
        foreach ($values as $i => $v) {
            $x = round($i * $stepX, 2);
            $y = round($height - ($v / 100) * ($height - 4) - 2, 2);
            $points[] = $x.','.$y;
        }
        $points[] = $width.','.$height;
        return 'M '.implode(' L ', $points).' Z';
    };
@endphp

<x-cachet::cachet>
    <x-cachet::header />

    <style>
        .es-shell { max-width: 1100px; margin: 0 auto; padding: 32px 16px 64px; }
        @media (min-width: 640px) { .es-shell { padding: 40px 24px 80px; } }

        /* ─────── Hero ─────── */
        .es-hero {
            position: relative;
            border-radius: 24px;
            padding: 32px;
            color: #fff;
            overflow: hidden;
            background: linear-gradient(135deg, {{ $overall['color'] }}, {{ $overall['color'] }}cc);
            box-shadow: 0 20px 50px -20px {{ $overall['color'] }}66;
        }
        .es-hero::before {
            content: ""; position: absolute; inset: 0;
            background:
                radial-gradient(circle at 20% 20%, rgba(255,255,255,0.20), transparent 40%),
                radial-gradient(circle at 80% 90%, rgba(0,0,0,0.18), transparent 50%);
            pointer-events: none;
        }
        .es-hero-row { position: relative; display: flex; align-items: center; gap: 20px; }
        .es-hero-icon {
            width: 56px; height: 56px; border-radius: 16px;
            background: rgba(255,255,255,0.25);
            display: flex; align-items: center; justify-content: center;
            backdrop-filter: blur(8px); flex-shrink: 0;
        }
        .es-hero-icon svg { width: 32px; height: 32px; }
        .es-hero-title { font-size: 24px; font-weight: 700; line-height: 1.2; margin: 0; letter-spacing: -0.01em; }
        @media (min-width: 640px) { .es-hero-title { font-size: 30px; } }
        .es-hero-sub { margin: 6px 0 0; font-size: 14px; opacity: 0.85; }

        @keyframes es-pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(255,255,255,0.6); }
            50% { box-shadow: 0 0 0 12px rgba(255,255,255,0); }
        }
        .es-hero-icon.is-active { animation: es-pulse 2s infinite; }

        /* ─────── Stats row ─────── */
        .es-stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 16px; }
        @media (min-width: 768px) { .es-stats { grid-template-columns: repeat(4, 1fr); } }
        .es-stat-card {
            background: rgba(255,255,255,0.85);
            border-radius: 16px; padding: 16px 18px;
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.5);
        }
        @media (prefers-color-scheme: dark) { .es-stat-card { background: rgba(24,24,27,0.7); border-color: rgba(255,255,255,0.08); } }
        .es-stat-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #71717a; margin: 0 0 6px; }
        .es-stat-value { font-size: 26px; font-weight: 700; line-height: 1; color: #18181b; letter-spacing: -0.02em; font-variant-numeric: tabular-nums; }
        @media (prefers-color-scheme: dark) { .es-stat-value { color: #fafafa; } }
        .es-stat-unit { font-size: 13px; font-weight: 500; color: #71717a; margin-left: 4px; }

        /* ─────── Sections ─────── */
        .es-section { margin-top: 40px; }
        .es-section-title {
            display: flex; align-items: baseline; justify-content: space-between;
            margin: 0 0 16px;
            font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em;
            color: #52525b;
        }
        @media (prefers-color-scheme: dark) { .es-section-title { color: #a1a1aa; } }
        .es-section-meta { font-size: 11px; font-weight: 500; color: #a1a1aa; }

        /* ─────── Incident card ─────── */
        .es-incident-card {
            position: relative;
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 8px 24px -16px rgba(0,0,0,0.12);
            border: 1px solid #f4f4f5;
            margin-bottom: 16px;
            overflow: hidden;
        }
        @media (prefers-color-scheme: dark) {
            .es-incident-card { background: #18181b; border-color: #27272a; box-shadow: 0 1px 3px rgba(0,0,0,0.4); }
        }
        .es-incident-card::before {
            content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
            background: var(--es-card-color, #a1a1aa);
        }
        .es-incident-header { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
        .es-incident-title { margin: 0; font-size: 17px; font-weight: 600; color: #18181b; text-decoration: none; }
        .es-incident-title:hover { text-decoration: underline; }
        @media (prefers-color-scheme: dark) { .es-incident-title { color: #fafafa; } }
        .es-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 10px; border-radius: 999px;
            font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
            color: #fff;
        }
        .es-incident-impact {
            display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px;
            font-size: 13px; color: #52525b;
        }
        @media (prefers-color-scheme: dark) { .es-incident-impact { color: #a1a1aa; } }
        .es-impact-tag {
            display: inline-flex; align-items: center; gap: 6px;
            background: #f4f4f5; padding: 4px 10px; border-radius: 999px;
            font-size: 12px; font-weight: 500;
        }
        @media (prefers-color-scheme: dark) { .es-impact-tag { background: #27272a; color: #e4e4e7; } }
        .es-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; }

        /* Timeline */
        .es-timeline { margin: 20px 0 0; padding: 0 0 0 24px; list-style: none; border-left: 2px solid #e4e4e7; }
        @media (prefers-color-scheme: dark) { .es-timeline { border-color: #3f3f46; } }
        .es-timeline-item { position: relative; padding-bottom: 18px; }
        .es-timeline-item:last-child { padding-bottom: 0; }
        .es-timeline-dot {
            position: absolute; left: -33px; top: 4px;
            width: 16px; height: 16px; border-radius: 50%;
            border: 4px solid #ffffff;
            background: var(--es-dot-color, #a1a1aa);
        }
        @media (prefers-color-scheme: dark) { .es-timeline-dot { border-color: #18181b; } }
        .es-timeline-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: 8px; }
        .es-timeline-status { font-size: 14px; font-weight: 600; color: #18181b; }
        @media (prefers-color-scheme: dark) { .es-timeline-status { color: #fafafa; } }
        .es-timeline-time { font-size: 12px; color: #71717a; font-variant-numeric: tabular-nums; }
        .es-timeline-tag {
            font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;
            background: #f4f4f5; color: #52525b;
            padding: 2px 8px; border-radius: 999px;
        }
        @media (prefers-color-scheme: dark) { .es-timeline-tag { background: #27272a; color: #a1a1aa; } }
        .es-timeline-body { margin: 6px 0 0; font-size: 14px; line-height: 1.55; color: #3f3f46; }
        @media (prefers-color-scheme: dark) { .es-timeline-body { color: #d4d4d8; } }
        .es-timeline-body p { margin: 0 0 8px; }
        .es-timeline-body p:last-child { margin-bottom: 0; }

        /* Maintenance card */
        .es-maint-card {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border: 1px solid #bfdbfe;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 12px;
        }
        @media (prefers-color-scheme: dark) {
            .es-maint-card { background: linear-gradient(135deg, #172554, #1e3a8a); border-color: #1e40af; }
        }
        .es-maint-title { display: flex; align-items: center; gap: 8px; margin: 0; font-size: 16px; font-weight: 600; color: #1e3a8a; }
        @media (prefers-color-scheme: dark) { .es-maint-title { color: #dbeafe; } }
        .es-maint-meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-top: 12px; font-size: 13px; color: #1e40af; }
        @media (prefers-color-scheme: dark) { .es-maint-meta { color: #bfdbfe; } }
        .es-maint-body { margin-top: 12px; font-size: 14px; line-height: 1.55; color: #1e3a8a; }
        @media (prefers-color-scheme: dark) { .es-maint-body { color: #e0e7ff; } }

        /* Component cards */
        .es-comp-grid { display: grid; grid-template-columns: 1fr; gap: 16px; }
        @media (min-width: 768px) { .es-comp-grid { grid-template-columns: repeat(2, 1fr); } }
        .es-comp-group {
            background: #ffffff; border: 1px solid #f4f4f5; border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            overflow: hidden;
        }
        @media (prefers-color-scheme: dark) { .es-comp-group { background: #18181b; border-color: #27272a; } }
        .es-comp-group-head {
            padding: 14px 18px;
            font-size: 13px; font-weight: 700; color: #18181b;
            border-bottom: 1px solid #f4f4f5;
            background: #fafafa;
        }
        @media (prefers-color-scheme: dark) { .es-comp-group-head { color: #fafafa; background: #09090b; border-color: #27272a; } }
        .es-comp-list { list-style: none; padding: 0; margin: 0; }
        .es-comp-item { padding: 16px 18px; border-bottom: 1px solid #f4f4f5; }
        .es-comp-item:last-child { border-bottom: 0; }
        @media (prefers-color-scheme: dark) { .es-comp-item { border-color: #27272a; } }
        .es-comp-row { display: flex; align-items: center; gap: 10px; }
        .es-comp-name { font-size: 14px; font-weight: 600; color: #18181b; flex: 1; }
        @media (prefers-color-scheme: dark) { .es-comp-name { color: #fafafa; } }
        .es-comp-status { font-size: 11px; font-weight: 600; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em; }
        .es-comp-uptime { font-size: 18px; font-weight: 700; font-variant-numeric: tabular-nums; color: #18181b; letter-spacing: -0.01em; }
        @media (prefers-color-scheme: dark) { .es-comp-uptime { color: #fafafa; } }
        .es-comp-uptime-suffix { font-size: 11px; font-weight: 500; color: #71717a; margin-left: 2px; }

        .es-spark { width: 100%; height: 36px; margin-top: 10px; display: block; }
        .es-bars90 { display: flex; gap: 1px; margin-top: 8px; height: 28px; border-radius: 4px; overflow: hidden; }
        .es-bars90 span { flex: 1; min-width: 1px; transition: transform 100ms ease-out; }
        .es-bars90 span:hover { transform: scaleY(1.2); }
        .es-bars90-axis {
            display: flex; justify-content: space-between;
            margin-top: 4px;
            font-size: 10px; color: #a1a1aa; font-weight: 500;
        }

        /* History */
        .es-history-day { margin-bottom: 16px; }
        .es-history-day-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #71717a; margin-bottom: 8px; }
        .es-history-list { list-style: none; padding: 0; margin: 0; }
        .es-history-item {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 16px; margin-bottom: 6px;
            background: #ffffff; border: 1px solid #f4f4f5; border-radius: 12px;
            font-size: 14px; text-decoration: none; color: #18181b;
            transition: background 100ms;
        }
        .es-history-item:hover { background: #fafafa; }
        @media (prefers-color-scheme: dark) {
            .es-history-item { background: #18181b; border-color: #27272a; color: #fafafa; }
            .es-history-item:hover { background: #27272a; }
        }
        .es-history-item-resolved { font-size: 11px; color: #10b981; font-weight: 600; margin-left: auto; text-transform: uppercase; letter-spacing: 0.05em; }
    </style>

    <main class="es-shell">

        {{-- ─────── Hero ─────── --}}
        <section class="es-hero">
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
                    <h1 class="es-hero-title">{{ $overall['label'] }}</h1>
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

        {{-- ─────── Active incidents ─────── --}}
        @if ($activeIncidents->isNotEmpty())
            <section class="es-section">
                <h2 class="es-section-title">
                    Incidents en cours
                    <span class="es-section-meta">{{ $activeIncidents->count() }} {{ $activeIncidents->count() > 1 ? 'incidents' : 'incident' }}</span>
                </h2>

                @foreach ($activeIncidents as $incident)
                    @php
                        $sortedUpdates = $incident->updates->sortByDesc('created_at')->values();
                        $latestStatus = $sortedUpdates->first()?->status ?? $incident->status;
                        $cardColor = $statusColor($latestStatus);
                    @endphp
                    <article class="es-incident-card" style="--es-card-color: {{ $cardColor }};">
                        <div class="es-incident-header">
                            <span class="es-pill" style="background: {{ $cardColor }};">{{ $latestStatus?->getLabel() ?? '—' }}</span>
                            <a class="es-incident-title" href="{{ route('cachet.status-page.incident', $incident) }}">{{ $incident->name }}</a>
                        </div>

                        @if ($incident->components->isNotEmpty())
                            <div class="es-incident-impact">
                                @foreach ($incident->components as $c)
                                    @php $cd = $componentDot($c); @endphp
                                    <span class="es-impact-tag"><span class="es-dot" style="background: {{ $cd['color'] }};"></span>{{ $c->name }}</span>
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

        {{-- ─────── Maintenance ─────── --}}
        @if ($schedules->isNotEmpty())
            <section class="es-section">
                <h2 class="es-section-title">
                    Maintenances planifiées
                    <span class="es-section-meta">{{ $schedules->count() }}</span>
                </h2>
                @foreach ($schedules as $schedule)
                    <article class="es-maint-card">
                        <h3 class="es-maint-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            {{ $schedule->name }}
                        </h3>
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

        {{-- ─────── Components ─────── --}}
        <section class="es-section">
            <h2 class="es-section-title">
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
                            'statusColor' => $statusColor,
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
                        'statusColor' => $statusColor,
                        'sparklinePath' => $sparklinePath,
                        'sparklineFill' => $sparklineFill,
                    ])
                @endif
            </div>
        </section>

        {{-- ─────── History ─────── --}}
        @if ($pastIncidents->isNotEmpty())
            <section class="es-section">
                <h2 class="es-section-title">Historique · 30 derniers jours</h2>
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
