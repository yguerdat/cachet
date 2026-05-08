<?php

namespace App\Listeners;

use App\Jobs\SendIncidentSms;
use App\Mail\IncidentNotificationMail;
use Cachet\Events\Incidents\IncidentCreated;
use Cachet\Events\Incidents\IncidentUpdated;
use Cachet\Models\Subscriber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;

class NotifySubscribersOfIncident
{
    /**
     * @param  IncidentCreated|IncidentUpdated  $event
     */
    public function handle(IncidentCreated|IncidentUpdated $event): void
    {
        $incident = $event->incident;

        if (! $incident->notifications) {
            return;
        }

        $isUpdate = $event instanceof IncidentUpdated;
        $componentIds = $incident->components()->pluck('components.id')->all();

        $this->subscribersFor($componentIds)->each(function (Subscriber $subscriber) use ($incident, $isUpdate): void {
            if ($subscriber->email !== null && $subscriber->verified_at !== null) {
                Mail::to($subscriber->email)->queue(new IncidentNotificationMail($incident, $subscriber, $isUpdate));
            }

            if ($subscriber->phone_number !== null && $subscriber->phone_verified_at !== null) {
                SendIncidentSms::dispatch($incident, $subscriber, $isUpdate);
            }
        });
    }

    /**
     * @param  array<int>  $componentIds
     * @return Collection<int, Subscriber>
     */
    private function subscribersFor(array $componentIds): Collection
    {
        return Subscriber::query()
            ->where(function (Builder $query) use ($componentIds): void {
                $query->where('global', true);

                if (! empty($componentIds)) {
                    $query->orWhereHas('components', function (Builder $q) use ($componentIds): void {
                        $q->whereIn('components.id', $componentIds);
                    });
                }
            })
            ->where(function (Builder $query): void {
                $query->whereNotNull('verified_at')->orWhereNotNull('phone_verified_at');
            })
            ->get();
    }
}
