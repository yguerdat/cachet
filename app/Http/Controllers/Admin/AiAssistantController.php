<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\IncidentWriter;
use App\Exceptions\AiAssistantException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAssistantController extends Controller
{
    public function __construct(private readonly IncidentWriter $writer)
    {
        // The route group is gated on Filament Authenticate (= logged-in user),
        // but we additionally require admin to keep AI spend and stored-content
        // authoring restricted to operators.
        $this->middleware(function ($request, $next) {
            if (! $request->user()?->isAdmin()) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function incident(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:4000'],
        ]);

        return $this->json(fn () => $this->writer->suggestIncident($validated['note']));
    }

    public function incidentUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:4000'],
            'context' => ['array'],
            'context.title' => ['nullable', 'string', 'max:255'],
            'context.status' => ['nullable', 'string', 'max:64'],
            'context.previous_updates' => ['array'],
            'context.previous_updates.*' => ['string', 'max:2000'],
        ]);

        return $this->json(fn () => [
            'description' => $this->writer->suggestIncidentUpdate(
                $validated['note'],
                $validated['context'] ?? [],
            ),
        ]);
    }

    public function maintenance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:4000'],
            'context' => ['array'],
            'context.scheduled_at' => ['nullable', 'string', 'max:64'],
            'context.completed_at' => ['nullable', 'string', 'max:64'],
        ]);

        return $this->json(fn () => $this->writer->suggestMaintenance(
            $validated['note'],
            $validated['context'] ?? [],
        ));
    }

    private function json(\Closure $generator): JsonResponse
    {
        try {
            $payload = $generator();
        } catch (AiAssistantException $exception) {
            return response()->json(['error' => $exception->getMessage()], 502);
        }

        return response()->json($payload);
    }
}
