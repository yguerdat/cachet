<?php

namespace App\Services\Uptime;

use Cachet\Enums\ComponentStatusEnum;
use Cachet\Enums\IncidentStatusEnum;
use Cachet\Models\Component;
use Cachet\Models\Incident;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Builds 90-day per-day status maps and an overall uptime percentage for each
 * component, derived from the incidents that touched the component in the
 * window. The pivot column `incident_components.component_status` provides the
 * declared severity per affected component, which we use as the "color" of
 * each day; absent that, we fall back to the incident's own status.
 */
class UptimeCalculator
{
    public function __construct(private readonly int $days = 90) {}

    /**
     * @param  Collection<int, Component>  $components
     * @return array<int, array{
     *     uptime_pct: float,
     *     days: array<string, array{status: string, label: string, color: string, count: int}>,
     * }>
     */
    public function compute(Collection $components): array
    {
        if ($components->isEmpty()) {
            return [];
        }

        $end = CarbonImmutable::now()->endOfDay();
        $start = $end->subDays($this->days - 1)->startOfDay();
        $componentIds = $components->pluck('id')->all();

        $incidents = Incident::query()
            ->with([
                'updates' => fn ($q) => $q->orderBy('created_at'),
                'components' => fn ($q) => $q->whereIn('components.id', $componentIds),
            ])
            ->whereHas('components', fn ($q) => $q->whereIn('components.id', $componentIds))
            ->where('created_at', '<=', $end)
            ->whereNull('deleted_at')
            ->get();

        $perComponent = [];

        foreach ($incidents as $incident) {
            $resolvedAt = $this->resolutionTimeFor($incident);

            // Filter out incidents whose entire active window is before our start.
            $effectiveEnd = $resolvedAt ?? CarbonImmutable::now();
            if ($effectiveEnd->lessThan($start)) {
                continue;
            }

            foreach ($incident->components as $component) {
                $componentStatus = $component->pivot->component_status ?? null;
                $perComponent[$component->id][] = [
                    'incident' => $incident,
                    'started_at' => CarbonImmutable::instance($incident->created_at),
                    'ended_at' => $resolvedAt,
                    'component_status' => $componentStatus,
                ];
            }
        }

        $result = [];
        foreach ($components as $component) {
            $result[$component->id] = $this->buildPerDay(
                $perComponent[$component->id] ?? [],
                $start,
                $end,
            );
        }

        return $result;
    }

    /**
     * @param  array<int, array{incident: Incident, started_at: CarbonImmutable, ended_at: ?CarbonImmutable, component_status: ?ComponentStatusEnum}>  $events
     */
    private function buildPerDay(array $events, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $days = [];
        $now = CarbonImmutable::now();
        $totalDownSeconds = 0;
        $totalSeconds = $end->diffInSeconds($start) ?: ($this->days * 86400);

        foreach (CarbonPeriod::create($start, '1 day', $end) as $day) {
            $dayKey = $day->format('Y-m-d');
            $dayStart = CarbonImmutable::instance($day)->startOfDay();
            $dayEnd = $dayStart->endOfDay();

            $worst = null;
            $touching = 0;

            foreach ($events as $event) {
                $eventEnd = $event['ended_at'] ?? $now;
                if ($event['started_at']->gt($dayEnd) || $eventEnd->lt($dayStart)) {
                    continue;
                }

                $touching++;

                $overlapStart = $event['started_at']->greaterThan($dayStart) ? $event['started_at'] : $dayStart;
                $overlapEnd = $eventEnd->lessThan($dayEnd) ? $eventEnd : $dayEnd;
                if ($overlapEnd->greaterThan($now)) {
                    $overlapEnd = $now;
                }
                $overlapSeconds = max(0, $overlapEnd->diffInSeconds($overlapStart, false));

                if ($overlapSeconds <= 0) {
                    continue;
                }

                $totalDownSeconds += $overlapSeconds;

                $candidate = $event['component_status'];
                if ($candidate === null || $candidate === ComponentStatusEnum::operational) {
                    $candidate = ComponentStatusEnum::performance_issues;
                }

                if ($worst === null || $candidate->value > $worst->value) {
                    $worst = $candidate;
                }
            }

            $days[$dayKey] = $this->describeDay($worst, $touching, $dayStart->greaterThan($now));
        }

        return [
            'uptime_pct' => round(max(0, min(100, (1 - ($totalDownSeconds / max(1, $totalSeconds))) * 100)), 2),
            'days' => $days,
        ];
    }

    /**
     * @return array{status: string, label: string, color: string, count: int}
     */
    private function describeDay(?ComponentStatusEnum $worst, int $count, bool $inFuture): array
    {
        if ($inFuture) {
            return ['status' => 'future', 'label' => 'à venir', 'color' => '#e4e4e7', 'count' => 0];
        }

        if ($worst === null) {
            return ['status' => 'ok', 'label' => 'Opérationnel', 'color' => '#22c55e', 'count' => 0];
        }

        return match ($worst) {
            ComponentStatusEnum::performance_issues => ['status' => 'degraded', 'label' => 'Lenteurs', 'color' => '#eab308', 'count' => $count],
            ComponentStatusEnum::partial_outage => ['status' => 'partial', 'label' => 'Panne partielle', 'color' => '#f97316', 'count' => $count],
            ComponentStatusEnum::major_outage => ['status' => 'major', 'label' => 'Panne majeure', 'color' => '#ef4444', 'count' => $count],
            ComponentStatusEnum::under_maintenance => ['status' => 'maintenance', 'label' => 'Maintenance', 'color' => '#3b82f6', 'count' => $count],
            default => ['status' => 'unknown', 'label' => 'Indisponible', 'color' => '#a1a1aa', 'count' => $count],
        };
    }

    private function resolutionTimeFor(Incident $incident): ?CarbonImmutable
    {
        if ($incident->status !== IncidentStatusEnum::fixed) {
            return null;
        }

        $fixedUpdate = $incident->updates
            ->where('status', IncidentStatusEnum::fixed)
            ->sortByDesc('created_at')
            ->first();

        $when = $fixedUpdate?->created_at ?? $incident->updated_at ?? Carbon::now();

        return CarbonImmutable::instance($when);
    }
}
