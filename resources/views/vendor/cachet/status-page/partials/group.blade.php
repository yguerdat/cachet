@php
    $componentDot = $componentDot ?? function ($component): array {
        return match ($component->status?->name) {
            'operational' => ['color' => '#22c55e', 'label' => 'Opérationnel'],
            'performance_issues' => ['color' => '#eab308', 'label' => 'Lenteurs'],
            'partial_outage' => ['color' => '#f97316', 'label' => 'Panne partielle'],
            'major_outage' => ['color' => '#ef4444', 'label' => 'Panne majeure'],
            'under_maintenance' => ['color' => '#3b82f6', 'label' => 'Maintenance'],
            default => ['color' => '#a1a1aa', 'label' => 'État inconnu'],
        };
    };
@endphp

<div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900/40">
    <div class="border-b border-zinc-200 bg-zinc-50/50 px-5 py-3 dark:border-zinc-800 dark:bg-zinc-900/60">
        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $title }}</h3>
    </div>
    <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
        @foreach ($components as $component)
            @php
                $dot = $componentDot($component);
                $uptime = $uptimeByComponent[$component->id] ?? null;
            @endphp
            <li class="px-5 py-4">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex h-2.5 w-2.5 rounded-full"
                          style="background: {{ $dot['color'] }};"
                          title="{{ $dot['label'] }}"></span>
                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $component->name }}</span>
                    <span class="ml-auto text-xs font-medium tabular-nums text-zinc-500 dark:text-zinc-400">
                        {{ $uptime ? number_format($uptime['uptime_pct'], 2, ',', ' ').' %' : '—' }}
                    </span>
                </div>

                @if ($uptime)
                    <div class="mt-3 flex items-center gap-px overflow-hidden rounded-sm" role="img" aria-label="Uptime sur 90 jours pour {{ $component->name }}">
                        @foreach ($uptime['days'] as $date => $day)
                            <span class="es-bar h-7 flex-1"
                                  style="background: {{ $day['color'] }}; min-width: 2px;"
                                  title="{{ \Carbon\CarbonImmutable::parse($date)->isoFormat('LL') }} — {{ $day['label'] }}@if($day['count']) ({{ $day['count'] }} incident{{ $day['count'] > 1 ? 's' : '' }})@endif"></span>
                        @endforeach
                    </div>
                    <div class="mt-1.5 flex justify-between text-[10px] text-zinc-400 dark:text-zinc-600">
                        <span>il y a 90 jours</span>
                        <span>aujourd'hui</span>
                    </div>
                @endif
            </li>
        @endforeach
    </ul>
</div>
