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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
        // ── Anti-bot layer #1 — honeypot.
        // Real users never see or fill the hidden "website" field; bots do.
        // Reply with the success page so they don't learn we filtered them.
        if (filled($request->input('website'))) {
            Log::info('Subscribe honeypot triggered', ['ip' => $request->ip()]);

            return redirect()->route('subscribe.create')
                ->with('status', __('subscribe.flash.email_sent'));
        }

        // ── Anti-bot layer #2 — min-time.
        // The form was rendered at most 2 seconds before this request. Bots
        // posting in <2s after a fresh fetch are almost certainly automated.
        $renderedAt = (int) $request->input('rendered_at', 0);
        if ($renderedAt > 0 && (time() - $renderedAt) < 2) {
            Log::info('Subscribe min-time triggered', ['ip' => $request->ip(), 'elapsed' => time() - $renderedAt]);

            return redirect()->route('subscribe.create')
                ->with('status', __('subscribe.flash.email_sent'));
        }

        $validated = $request->validate([
            'email' => ['nullable', 'email:rfc,strict'],
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

        // ── Anti-bot layer #3 — verification cooldown.
        // Only resend the email/SMS verification if at least 60 seconds have
        // elapsed since the last send to this subscriber. Prevents inbox-bombing
        // by repeated form submissions.
        if (! empty($validated['email']) && $subscriber->verified_at === null && $this->canResendEmail($subscriber)) {
            Mail::to($subscriber->email)->queue(new SubscriberVerifyEmail($subscriber));
            $this->markEmailSent($subscriber);
        }

        if (! empty($validated['phone_number']) && $subscriber->phone_verified_at === null && $this->canResendSms($subscriber)) {
            $phoneVerifier->sendCode($subscriber);
            $this->markSmsSent($subscriber);
        }

        if (! empty($validated['phone_number'])) {
            return redirect()->route('subscribe.verify-phone', $subscriber);
        }

        return redirect()->route('subscribe.create')
            ->with('status', __('subscribe.flash.email_sent'));
    }

    private function canResendEmail(Subscriber $s): bool
    {
        return ! Cache::has('subscriber_email_cooldown:'.$s->getKey());
    }

    private function markEmailSent(Subscriber $s): void
    {
        Cache::put('subscriber_email_cooldown:'.$s->getKey(), 1, 60);
    }

    private function canResendSms(Subscriber $s): bool
    {
        return ! Cache::has('subscriber_sms_cooldown:'.$s->getKey());
    }

    private function markSmsSent(Subscriber $s): void
    {
        Cache::put('subscriber_sms_cooldown:'.$s->getKey(), 1, 60);
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

        // Cachet's core Subscriber model has phone_number outside $fillable,
        // so mass-assignment silently drops it. Use forceFill to bypass.
        if ($existing) {
            return tap($existing, function (Subscriber $s) use ($email, $phone, $validated): void {
                $s->forceFill([
                    'email' => $email ?? $s->email,
                    'phone_number' => $phone ?? $s->phone_number,
                    'global' => (bool) ($validated['global'] ?? $s->global),
                ])->save();
            });
        }

        $subscriber = new Subscriber;
        $subscriber->forceFill([
            'email' => $email,
            'phone_number' => $phone,
            'verify_code' => Str::random(42),
            'global' => (bool) ($validated['global'] ?? false),
        ])->save();

        return $subscriber;
    }
}
