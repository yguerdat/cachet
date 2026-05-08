<?php

namespace App\Http\Controllers\StatusPage;

use App\Http\Controllers\Controller;
use Cachet\Models\Schedule;
use Illuminate\Contracts\View\View;

class ScheduleController extends Controller
{
    public function show(Schedule $schedule): View
    {
        return view('status-page.schedule', [
            'schedule' => $schedule->loadMissing([
                'components',
                'updates' => fn ($q) => $q->orderByDesc('created_at'),
            ]),
        ]);
    }
}
