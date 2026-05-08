<?php

namespace App\Http\Controllers\Subscribe;

use App\Http\Controllers\Controller;
use App\Mail\SubscriberVerifyEmail;
use App\Services\Subscribers\PhoneVerifier;
use Cachet\Models\Component;
use Cachet\Models\ComponentGroup;
use Cachet\Models\Subscriber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SubscribeController extends Controller
{
    public function create(): View
    {
        return view('subscribe.index', [
            'componentGroups' => ComponentGroup::query()
                ->with(['components' => fn ($q) => $q->enabled()->orderBy('order')])
                ->orderBy('order')
                ->get(),
            'ungroupedComponents' => Component::query()
                ->enabled()
                ->whereNull('component_group_id')
                ->orderBy('order')
                ->get(),
        ]);
    }

    public function store(Request $request, PhoneVerifier $phoneVerifier): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'email:rfc'],
            'phone_number' => ['nullable', 'regex:/^\+[1-9]\d{6,14}$/'],
            'components' => ['array'],
            'components.*' => ['integer', 'exists:components,id'],
            'global' => ['nullable', 'boolean'],
        ]);

        if (empty($validated['email']) && empty($validated['phone_number'])) {
            return back()
                ->withInput()
                ->withErrors(['email' => __('subscribe.validation.email_or_phone_required')]);
        }

        $subscriber = $this->resolveSubscriber($validated);

        $componentIds = $validated['components'] ?? [];
        if (! empty($componentIds) && ! ($validated['global'] ?? false)) {
            $subscriber->components()->sync($componentIds);
        } else {
            $subscriber->components()->detach();
        }

        if (! empty($validated['email']) && $subscriber->verified_at === null) {
            Mail::to($subscriber->email)->queue(new SubscriberVerifyEmail($subscriber));
        }

        if (! empty($validated['phone_number']) && $subscriber->phone_verified_at === null) {
            $phoneVerifier->sendCode($subscriber);
        }

        if (! empty($validated['phone_number'])) {
            return redirect()->route('subscribe.verify-phone', $subscriber);
        }

        return redirect()->route('subscribe.create')
            ->with('status', __('subscribe.flash.email_sent'));
    }

    public function verifyEmail(Subscriber $subscriber, string $code): RedirectResponse
    {
        if (! hash_equals((string) $subscriber->verify_code, $code)) {
            abort(404);
        }

        $subscriber->verify();

        return redirect()->route('subscribe.manage', $subscriber)
            ->with('status', __('subscribe.flash.email_verified'));
    }

    public function showVerifyPhone(Subscriber $subscriber): View
    {
        return view('subscribe.verify-phone', ['subscriber' => $subscriber]);
    }

    public function verifyPhone(Request $request, Subscriber $subscriber, PhoneVerifier $phoneVerifier): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        if (! $phoneVerifier->verifyCode($subscriber, $validated['code'])) {
            return back()->withErrors(['code' => __('subscribe.validation.invalid_phone_code')]);
        }

        return redirect()->route('subscribe.manage', $subscriber)
            ->with('status', __('subscribe.flash.phone_verified'));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveSubscriber(array $validated): Subscriber
    {
        $email = $validated['email'] ?? null;
        $phone = $validated['phone_number'] ?? null;

        $existing = Subscriber::query()
            ->when($email, fn ($q) => $q->orWhere('email', $email))
            ->when($phone, fn ($q) => $q->orWhere('phone_number', $phone))
            ->first();

        if ($existing) {
            $existing->fill([
                'email' => $email ?? $existing->email,
                'phone_number' => $phone ?? $existing->phone_number,
                'global' => (bool) ($validated['global'] ?? $existing->global),
            ])->save();

            return $existing;
        }

        return Subscriber::create([
            'email' => $email,
            'phone_number' => $phone,
            'verify_code' => Str::random(42),
            'global' => (bool) ($validated['global'] ?? false),
        ]);
    }
}
