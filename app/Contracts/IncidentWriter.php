<?php

namespace App\Contracts;

interface IncidentWriter
{
    /**
     * Generate a suggested {title, description} for a new incident.
     *
     * @return array{title: string, description: string}
     */
    public function suggestIncident(string $rawNote): array;

    /**
     * Generate a suggested description for a status update on an existing incident.
     *
     * @param  array<string, mixed>  $incidentContext  e.g. ['title' => string, 'previous_updates' => string[]]
     */
    public function suggestIncidentUpdate(string $rawNote, array $incidentContext): string;

    /**
     * Generate a suggested {title, description} for a planned maintenance window.
     *
     * @param  array<string, mixed>  $context  e.g. ['scheduled_at' => string, 'completed_at' => string]
     * @return array{title: string, description: string}
     */
    public function suggestMaintenance(string $rawNote, array $context = []): array;
}
