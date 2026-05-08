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
            $x = round($i * $stepX, 2);
            $y = round($height - ($v / 100) * ($height - 4) - 2, 2);
            $points[] = $x.','.$y;
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
            $x = round($i * $stepX, 2);
            $y = round($height - ($v / 100) * ($height - 4) - 2, 2);
            $points[] = $x.','.$y;
        }
        $points[] = $width.','.$height;
        return 'M '.implode(' L ', $points).' Z';
    };
@endphp

<x-cachet::cachet>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600;9..144,700;9..144,800&display=swap');

        /* ─────── Brand vars ─────── */
        :root {
            --es-brand: #003da5;
            --es-brand-deep: #002a73;
            --es-brand-soft: rgba(0, 61, 165, 0.06);
            --es-brand-mid: rgba(0, 61, 165, 0.15);
            --es-bg: #f7f8fc;
            --es-card: #ffffff;
            --es-text: #1f2937;
            --es-text-soft: #6b7280;
            --es-text-mute: #9ca3af;
            --es-border: #e5e7eb;
            --es-radius: 18px;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --es-bg: #0b0d12;
                --es-card: #14171d;
                --es-text: #f3f4f6;
                --es-text-soft: #9ca3af;
                --es-text-mute: #6b7280;
                --es-border: #1f242b;
            }
        }

        body { background: var(--es-bg) !important; }
        .es-shell, .es-shell *, .es-header, .es-header * { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
        .es-display { font-family: 'Fraunces', Georgia, 'Times New Roman', serif; font-feature-settings: "ss01", "ss02"; letter-spacing: -0.02em; }

        /* ─────── Header ─────── */
        .es-header {
            background: var(--es-card);
            border-bottom: 1px solid var(--es-border);
        }
        .es-header-inner {
            max-width: 1100px; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px; padding: 18px 24px;
        }
        .es-logo-link { display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .es-logo-mark {
            width: 44px; height: 44px; border-radius: 10px;
            background: var(--es-brand); color: white;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 22px; flex-shrink: 0;
            box-shadow: 0 4px 14px -4px rgba(0, 61, 165, 0.45);
        }
        .es-logo-text { line-height: 1.1; }
        .es-logo-name {
            font-family: 'Fraunces', serif; font-size: 22px; font-weight: 700;
            color: var(--es-text); letter-spacing: -0.02em;
        }
        .es-logo-tag { font-size: 11px; color: var(--es-text-soft); font-weight: 500; letter-spacing: 0.02em; }

        .es-subscribe-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 18px; border-radius: 999px;
            background: linear-gradient(135deg, var(--es-brand), var(--es-brand-deep));
            color: #ffffff; text-decoration: none;
            font-size: 14px; font-weight: 600;
            box-shadow: 0 4px 14px -4px rgba(0, 61, 165, 0.55), inset 0 1px 0 rgba(255,255,255,0.2);
            transition: transform 120ms ease-out, box-shadow 120ms ease-out;
        }
        .es-subscribe-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 20px -4px rgba(0, 61, 165, 0.6); }
        .es-subscribe-btn svg { width: 16px; height: 16px; flex-shrink: 0; }

        /* ─────── Shell ─────── */
        .es-shell { max-width: 1100px; margin: 0 auto; padding: 32px 24px 80px; color: var(--es-text); }
        @media (max-width: 640px) { .es-shell { padding: 24px 16px 64px; } }

        /* ─────── Hero ─────── */
        .es-hero {
            position: relative;
            border-radius: 24px;
            padding: 36px;
            color: #fff;
            overflow: hidden;
            background:
                radial-gradient(circle at 100% 0%, rgba(255,255,255,0.18), transparent 50%),
                radial-gradient(circle at 0% 100%, rgba(0,0,0,0.20), transparent 60%),
                linear-gradient(135deg, {{ $overall['color'] }} 0%, {{ $overall['color'] }}dd 100%);
            box-shadow: 0 24px 60px -24px {{ $overall['color'] }}88;
        }
        .es-hero-row { display: flex; align-items: center; gap: 22px; }
        .es-hero-icon {
            width: 64px; height: 64px; border-radius: 18px;
            background: rgba(255,255,255,0.18);
            backdrop-filter: blur(10px);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; border: 1px solid rgba(255,255,255,0.3);
        }
        .es-hero-icon svg { width: 32px; height: 32px; }
        .es-hero-title { margin: 0; font-size: 32px; font-weight: 700; line-height: 1.1; }
        @media (min-width: 640px) { .es-hero-title { font-size: 38px; } }
        .es-hero-sub { margin: 8px 0 0; font-size: 15px; opacity: 0.9; }

        @keyframes es-pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(255,255,255,0.6); }
            50% { box-shadow: 0 0 0 16px rgba(255,255,255,0); }
        }
        .es-hero-icon.is-active { animation: es-pulse 2.4s infinite; }

        /* ─────── Stats ─────── */
        .es-stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 24px; }
        @media (min-width: 768px) { .es-stats { grid-template-columns: repeat(4, 1fr); } }
        .es-stat-card {
            background: rgba(255,255,255,0.92);
            border-radius: 16px; padding: 18px 20px;
            border: 1px solid rgba(255,255,255,0.6);
        }
        @media (prefers-color-scheme: dark) { .es-stat-card { background: rgba(20,23,29,0.85); border-color: rgba(255,255,255,0.08); } }
        .es-stat-label { margin: 0 0 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--es-text-soft); }
        .es-stat-value { margin: 0; font-size: 28px; font-weight: 700; line-height: 1; color: var(--es-text); letter-spacing: -0.02em; font-variant-numeric: tabular-nums; }
        .es-stat-unit { font-size: 13px; font-weight: 500; color: var(--es-text-soft); margin-left: 4px; }

        /* ─────── Sections ─────── */
        .es-section { margin-top: 48px; }
        .es-section-title {
            display: flex; align-items: baseline; justify-content: space-between;
            margin: 0 0 18px;
            font-family: 'Fraunces', serif; font-size: 22px; font-weight: 700;
            color: var(--es-text); letter-spacing: -0.01em;
        }
        .es-section-meta {
            font-family: 'Inter', sans-serif; font-size: 12px; font-weight: 600;
            color: var(--es-text-mute); text-transform: uppercase; letter-spacing: 0.06em;
        }

        /* ─────── Incidents ─────── */
        .es-incident-card {
            position: relative;
            background: var(--es-card);
            border-radius: var(--es-radius);
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 12px 32px -16px rgba(0,0,0,0.10);
            border: 1px solid var(--es-border);
            margin-bottom: 16px;
            overflow: hidden;
        }
        .es-incident-card::before {
            content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
            background: var(--es-card-color, var(--es-brand));
        }
        .es-incident-header { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
        .es-incident-title { margin: 0; font-family: 'Fraunces', serif; font-size: 18px; font-weight: 700; color: var(--es-text); text-decoration: none; letter-spacing: -0.01em; }
        .es-incident-title:hover { text-decoration: underline; }

        .es-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px; border-radius: 999px;
            font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
            color: #fff;
        }

        .es-incident-impact { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
        .es-impact-tag {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--es-brand-soft); color: var(--es-brand);
            padding: 6px 12px; border-radius: 999px;
            font-size: 13px; font-weight: 500;
            border: 1px solid var(--es-brand-mid);
        }
        .es-impact-tag img { width: 16px; height: 16px; border-radius: 4px; flex-shrink: 0; }
        .es-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; }

        .es-timeline {
            margin: 22px 0 0; padding: 0 0 0 26px; list-style: none;
            border-left: 2px solid var(--es-border);
        }
        .es-timeline-item { position: relative; padding-bottom: 18px; }
        .es-timeline-item:last-child { padding-bottom: 0; }
        .es-timeline-dot {
            position: absolute; left: -34px; top: 4px;
            width: 16px; height: 16px; border-radius: 50%;
            border: 4px solid var(--es-card);
            background: var(--es-dot-color, var(--es-brand));
        }
        .es-timeline-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: 8px; }
        .es-timeline-status { font-size: 14px; font-weight: 600; color: var(--es-text); }
        .es-timeline-time { font-size: 12px; color: var(--es-text-soft); font-variant-numeric: tabular-nums; }
        .es-timeline-tag {
            font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
            background: var(--es-brand-soft); color: var(--es-brand);
            padding: 3px 8px; border-radius: 999px;
        }
        .es-timeline-body { margin: 6px 0 0; font-size: 14px; line-height: 1.6; color: var(--es-text-soft); }
        .es-timeline-body p { margin: 0 0 8px; }
        .es-timeline-body p:last-child { margin: 0; }

        /* ─────── Maintenance ─────── */
        .es-maint-card {
            background: linear-gradient(135deg, rgba(0,61,165,0.08), rgba(0,61,165,0.02));
            border: 1px solid var(--es-brand-mid);
            border-radius: var(--es-radius);
            padding: 22px 24px;
            margin-bottom: 12px;
        }
        .es-maint-title { display: flex; align-items: center; gap: 10px; margin: 0; font-family: 'Fraunces', serif; font-size: 17px; font-weight: 700; color: var(--es-brand); }
        .es-maint-meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-top: 14px; font-size: 13px; color: var(--es-text); }
        .es-maint-body { margin-top: 14px; font-size: 14px; line-height: 1.6; color: var(--es-text-soft); }

        /* ─────── Components ─────── */
        .es-comp-grid { display: grid; grid-template-columns: 1fr; gap: 16px; }
        .es-comp-group {
            background: var(--es-card); border: 1px solid var(--es-border); border-radius: var(--es-radius);
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            overflow: hidden;
        }
        .es-comp-group-head {
            padding: 14px 20px;
            font-family: 'Fraunces', serif; font-size: 15px; font-weight: 700;
            color: var(--es-text);
            border-bottom: 1px solid var(--es-border);
            background: var(--es-brand-soft);
        }
        .es-comp-list { list-style: none; padding: 0; margin: 0; }
        .es-comp-item { padding: 18px 20px; border-bottom: 1px solid var(--es-border); }
        .es-comp-item:last-child { border-bottom: 0; }
        .es-comp-row { display: flex; align-items: center; gap: 12px; }
        .es-comp-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: var(--es-brand-soft); flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; overflow: hidden;
            border: 1px solid var(--es-brand-mid);
        }
        .es-comp-icon img { width: 100%; height: 100%; object-fit: contain; padding: 4px; }
        .es-comp-icon-fallback {
            width: 12px; height: 12px; border-radius: 50%;
            background: var(--es-brand);
        }
        .es-comp-name { font-size: 15px; font-weight: 600; color: var(--es-text); flex: 1; }
        .es-comp-status {
            font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;
        }
        .es-comp-uptime {
            font-size: 20px; font-weight: 700; font-variant-numeric: tabular-nums; letter-spacing: -0.02em;
            color: var(--es-text);
        }
        .es-comp-uptime-suffix { font-size: 11px; font-weight: 500; color: var(--es-text-mute); margin-left: 2px; }

        .es-spark { width: 100%; height: 38px; margin-top: 12px; display: block; }
        .es-bars90 { display: flex; gap: 1px; margin-top: 8px; height: 30px; border-radius: 4px; overflow: hidden; }
        .es-bars90 span { flex: 1; min-width: 1px; transition: transform 100ms ease-out; }
        .es-bars90 span:hover { transform: scaleY(1.15); }
        .es-bars90-axis {
            display: flex; justify-content: space-between;
            margin-top: 6px;
            font-size: 10px; color: var(--es-text-mute); font-weight: 500;
        }

        /* ─────── History ─────── */
        .es-history-day { margin-bottom: 18px; }
        .es-history-day-label {
            font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
            color: var(--es-text-mute); margin-bottom: 8px;
        }
        .es-history-list { list-style: none; padding: 0; margin: 0; }
        .es-history-item {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 18px; margin-bottom: 6px;
            background: var(--es-card); border: 1px solid var(--es-border); border-radius: 12px;
            font-size: 14px; text-decoration: none; color: var(--es-text);
            transition: background 100ms;
        }
        .es-history-item:hover { background: var(--es-brand-soft); }
        .es-history-item-resolved {
            font-size: 11px; color: #10b981; font-weight: 700; margin-left: auto;
            text-transform: uppercase; letter-spacing: 0.06em;
        }
    </style>

    {{-- ─────── Custom header (replaces <x-cachet::header />) ─────── --}}
    <header class="es-header">
        <div class="es-header-inner">
            <a href="{{ url(\Cachet\Cachet::path()) }}" class="es-logo-link">
                <span class="es-logo-mark es-display">eS</span>
                <span class="es-logo-text">
                    <span class="es-logo-name">eSéances</span><br>
                    <span class="es-logo-tag">Status · powered by Artionet</span>
                </span>
            </a>
            <a href="{{ route('subscribe.create') }}" class="es-subscribe-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                S'abonner aux notifications
            </a>
        </div>
    </header>

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

        {{-- ─────── Active incidents ─────── --}}
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
                    <article class="es-incident-card" style="--es-card-color: {{ $cardColor }};">
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

        {{-- ─────── Maintenance ─────── --}}
        @if ($schedules->isNotEmpty())
            <section class="es-section">
                <h2 class="es-section-title es-display">
                    Maintenances planifiées
                    <span class="es-section-meta">{{ $schedules->count() }}</span>
                </h2>
                @foreach ($schedules as $schedule)
                    <article class="es-maint-card">
                        <h3 class="es-maint-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
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

        {{-- ─────── History ─────── --}}
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
