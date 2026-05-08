<?php

namespace App\Http\Controllers\Subscribe;

use App\Http\Controllers\Controller;
use Cachet\Models\Component;
use Cachet\Models\ComponentGroup;
use Cachet\Models\Subscriber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ManageController extends Controller
{
    public function edit(Subscriber $subscriber): View
    {
        return view('subscribe.manage', [
            'subscriber' => $subscriber,
            'componentGroups' => ComponentGroup::query()
                ->with(['components' => fn ($q) => $q->enabled()->orderBy('order')])
                ->orderBy('order')
                ->get(),
            'ungroupedComponents' => Component::query()
                ->enabled()
                ->whereNull('component_group_id')
                ->orderBy('order')
                ->get(),
            'subscribedComponentIds' => $subscriber->components()->pluck('components.id')->all(),
        ]);
    }

    public function update(Request $request, Subscriber $subscriber): RedirectResponse
    {
        $validated = $request->validate([
            'global' => ['nullable', 'boolean'],
            'components' => ['array'],
            'components.*' => ['integer', 'exists:components,id'],
        ]);

        $global = (bool) ($validated['global'] ?? false);

        $subscriber->forceFill(['global' => $global])->save();

        if ($global) {
            $subscriber->components()->detach();
        } else {
            $subscriber->components()->sync($validated['components'] ?? []);
        }

        return redirect()->route('subscribe.manage', $subscriber)
            ->with('status', __('subscribe.flash.preferences_saved'));
    }
}
