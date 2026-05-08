<?php

namespace App\View\Composers;

use App\Services\Uptime\UptimeCalculator;
use Cachet\Enums\ComponentStatusEnum;
use Cachet\Enums\IncidentStatusEnum;
use Cachet\Models\Component;
use Cachet\Models\ComponentGroup;
use Cachet\Models\Incident;
use Illuminate\View\View;

class StatusPageComposer
{
    public function __construct(private readonly UptimeCalculator $uptime) {}

    public function compose(View $view): void
    {
        $componentGroups = $view->getData()['componentGroups'] ?? collect();
        $ungrouped = $view->getData()['ungroupedComponents'] ?? collect();

        $allComponents = collect();
        foreach ($componentGroups as $group) {
            foreach ($group->components as $component) {
                $allComponents->push($component);
            }
        }
        foreach ($ungrouped as $component) {
            $allComponents->push($component);
        }

        $uptimeByComponent = $this->uptime->compute($allComponents);

        $activeIncidents = Incident::query()
            ->with([
                'components',
                'updates' => fn ($q) => $q->orderByDesc('created_at'),
            ])
            ->whereIn('status', IncidentStatusEnum::unresolved())
            ->orderByDesc('created_at')
            ->get();

        $pastIncidents = Incident::query()
            ->with([
                'components',
                'updates' => fn ($q) => $q->orderByDesc('created_at'),
            ])
            ->where('status', IncidentStatusEnum::fixed)
            ->where('created_at', '>=', now()->subDays(30))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $overall = $this->overallStatus($activeIncidents, $allComponents);

        $view->with([
            'uptimeByComponent' => $uptimeByComponent,
            'activeIncidents' => $activeIncidents,
            'pastIncidents' => $pastIncidents,
            'overall' => $overall,
        ]);
    }

    /**
     * @param  iterable<Incident>  $activeIncidents
     * @param  iterable<Component>  $components
     * @return array{status: string, label: string, color: string, accent: string}
     */
    private function overallStatus(iterable $activeIncidents, iterable $components): array
    {
        $worst = null;
        foreach ($activeIncidents as $incident) {
            foreach ($incident->components as $component) {
                $status = $component->pivot->component_status ?? null;
                if ($status === null || $status === ComponentStatusEnum::operational) {
                    $status = ComponentStatusEnum::performance_issues;
                }
                if ($worst === null || $status->value > $worst->value) {
                    $worst = $status;
                }
            }
        }

        if ($worst === null) {
            foreach ($components as $component) {
                $status = $component->status ?? null;
                if ($status !== null && $status !== ComponentStatusEnum::operational
                    && ($worst === null || $status->value > $worst->value)) {
                    $worst = $status;
                }
            }
        }

        return match ($worst) {
            ComponentStatusEnum::performance_issues => [
                'status' => 'degraded', 'label' => 'Lenteurs détectées sur certains services',
                'color' => '#eab308', 'accent' => 'rgba(234, 179, 8, 0.12)',
            ],
            ComponentStatusEnum::partial_outage => [
                'status' => 'partial', 'label' => 'Panne partielle en cours',
                'color' => '#f97316', 'accent' => 'rgba(249, 115, 22, 0.12)',
            ],
            ComponentStatusEnum::major_outage => [
                'status' => 'major', 'label' => 'Panne majeure en cours',
                'color' => '#ef4444', 'accent' => 'rgba(239, 68, 68, 0.12)',
            ],
            ComponentStatusEnum::under_maintenance => [
                'status' => 'maintenance', 'label' => 'Maintenance en cours',
                'color' => '#3b82f6', 'accent' => 'rgba(59, 130, 246, 0.12)',
            ],
            null, ComponentStatusEnum::operational => [
                'status' => 'ok', 'label' => 'Tous les systèmes sont opérationnels',
                'color' => '#22c55e', 'accent' => 'rgba(34, 197, 94, 0.12)',
            ],
            default => [
                'status' => 'unknown', 'label' => 'État indéterminé',
                'color' => '#a1a1aa', 'accent' => 'rgba(161, 161, 170, 0.12)',
            ],
        };
    }
}
