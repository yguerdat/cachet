<?php

namespace App\Observers;

use App\Jobs\SendScheduleSms;
use App\Mail\ScheduleNotificationMail;
use Cachet\Models\Schedule;
use Cachet\Models\Subscriber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;

class ScheduleObserver
{
    public function created(Schedule $schedule): void
    {
        $this->notify($schedule, isUpdate: false);
    }

    public function updated(Schedule $schedule): void
    {
        $this->notify($schedule, isUpdate: true);
    }

    private function notify(Schedule $schedule, bool $isUpdate): void
    {
        $componentIds = $schedule->components()->pluck('components.id')->all();

        $this->subscribersFor($componentIds)->each(function (Subscriber $subscriber) use ($schedule, $isUpdate): void {
            if ($subscriber->email !== null && $subscriber->verified_at !== null) {
                Mail::to($subscriber->email)->queue(new ScheduleNotificationMail($schedule, $subscriber, $isUpdate));
            }

            if ($subscriber->phone_number !== null && $subscriber->phone_verified_at !== null) {
                SendScheduleSms::dispatch($schedule, $subscriber, $isUpdate);
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
