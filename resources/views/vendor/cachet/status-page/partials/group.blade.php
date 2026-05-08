<div class="es-comp-group" style="grid-column: 1 / -1;">
    <div class="es-comp-group-head">{{ $title }}</div>
    <ul class="es-comp-list">
        @foreach ($components as $component)
            @php
                $cd = $componentDot($component);
                $uptime = $uptimeByComponent[$component->id] ?? null;
                $logo = $productIcon($component);
                $sparkD = $uptime ? $sparklinePath($uptime['days']) : '';
                $sparkF = $uptime ? $sparklineFill($uptime['days']) : '';
            @endphp
            <li class="es-comp-item">
                <div class="es-comp-row">
                    <span class="es-comp-icon">
                        @if ($logo)
                            <img src="{{ asset($logo) }}" alt="">
                        @else
                            <span class="es-comp-icon-fallback" style="background: {{ $cd['color'] }};"></span>
                        @endif
                    </span>
                    <div class="es-comp-text">
                        <span class="es-comp-name">{{ $component->name }}</span>
                        @if (filled($component->description))
                            <span class="es-comp-desc">{{ $component->description }}</span>
                        @endif
                    </div>
                    <span class="es-comp-status" style="color: {{ $cd['color'] }};">{{ $cd['label'] }}</span>
                    @if ($uptime)
                        <span class="es-comp-uptime">{{ number_format($uptime['uptime_pct'], 2, ',', ' ') }}<span class="es-comp-uptime-suffix">%</span></span>
                    @endif
                </div>

                @if ($uptime)
                    <svg class="es-spark" viewBox="0 0 200 36" preserveAspectRatio="none" aria-hidden="true">
                        <defs>
                            <linearGradient id="es-grad-{{ $component->id }}" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="{{ $cd['color'] }}" stop-opacity="0.32" />
                                <stop offset="100%" stop-color="{{ $cd['color'] }}" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <path d="{{ $sparkF }}" fill="url(#es-grad-{{ $component->id }})" />
                        <path d="{{ $sparkD }}" fill="none" stroke="{{ $cd['color'] }}" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" />
                    </svg>

                    <div class="es-bars90" role="img" aria-label="Uptime sur 90 jours">
                        @foreach ($uptime['days'] as $date => $day)
                            <span style="background: {{ $day['color'] }};" title="{{ \Carbon\CarbonImmutable::parse($date)->isoFormat('LL') }} — {{ $day['label'] }}@if ($day['count']) ({{ $day['count'] }} incident{{ $day['count'] > 1 ? 's' : '' }})@endif"></span>
                        @endforeach
                    </div>
                    <div class="es-bars90-axis"><span>il y a 90 jours</span><span>aujourd'hui</span></div>
                @endif
            </li>
        @endforeach
    </ul>
</div>
