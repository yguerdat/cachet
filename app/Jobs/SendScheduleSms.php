<?php

namespace App\Jobs;

use App\Contracts\SmsSender;
use App\Contracts\UrlShortener;
use Cachet\Cachet;
use Cachet\Models\Schedule;
use Cachet\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class SendScheduleSms implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly Schedule $schedule,
        public readonly Subscriber $subscriber,
        public readonly bool $isUpdate,
    ) {}

    public function handle(SmsSender $smsSender, UrlShortener $urlShortener): void
    {
        if ($this->subscriber->phone_number === null || $this->subscriber->phone_verified_at === null) {
            return;
        }

        $longUrl = url(Cachet::path());
        $shortUrl = $urlShortener->shorten($longUrl, 's'.$this->schedule->getKey());

        $title = Str::limit($this->schedule->name, 60, '…');
        $prefix = $this->isUpdate ? __('sms.schedule.update_prefix') : __('sms.schedule.new_prefix');
        $body = sprintf('[%s] %s — %s', $prefix, $title, $shortUrl);

        $smsSender->send($this->subscriber->phone_number, $body);
    }
}
