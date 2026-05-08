<?php

namespace App\Http\Controllers\Subscribe;

use App\Http\Controllers\Controller;
use Cachet\Models\Subscriber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class UnsubscribeController extends Controller
{
    public function confirm(Subscriber $subscriber): View
    {
        return view('subscribe.unsubscribe', ['subscriber' => $subscriber]);
    }

    public function destroy(Subscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();

        return redirect()->route('subscribe.create')
            ->with('status', __('subscribe.flash.unsubscribed'));
    }
}
