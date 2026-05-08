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

        // ── Anti-bot layer #3 — verification cooldowns.
        // Per-subscriber cooldown (60s) prevents accidental double-clicks.
        // Per-channel cooldown keyed by SHA1(value) (15 min) plus a daily
        // global budget protect against IP-rotating attackers who would
        // otherwise SMS-bomb a victim or burn our mail / SMS quota.
        if (! empty($validated['email']) && $subscriber->verified_at === null) {
            if ($this->canResendEmail($subscriber)
                && $this->canSendToEmail($validated['email'])
                && $this->underDailyEmailBudget()) {
                Mail::to($subscriber->email)->queue(new SubscriberVerifyEmail($subscriber));
                $this->markEmailSent($subscriber);
                $this->markEmailAddressSent($validated['email']);
                $this->bumpDailyEmailCounter();
            } else {
                Log::info('Subscribe email send skipped', [
                    'subscriber_id' => $subscriber->getKey(),
                    'reason' => 'cooldown_or_budget',
                ]);
            }
        }

        if (! empty($validated['phone_number']) && $subscriber->phone_verified_at === null) {
            if ($this->canResendSms($subscriber)
                && $this->canSendToPhone($validated['phone_number'])
                && $this->underDailySmsBudget()) {
                $phoneVerifier->sendCode($subscriber);
                $this->markSmsSent($subscriber);
                $this->markPhoneSent($validated['phone_number']);
                $this->bumpDailySmsCounter();
            } else {
                Log::info('Subscribe SMS send skipped', [
                    'subscriber_id' => $subscriber->getKey(),
                    'reason' => 'cooldown_or_budget',
                ]);
            }
        }

        if (! empty($validated['phone_number'])) {
            return redirect()->route('subscribe.verify-phone', $subscriber);
        }

        return redirect()->route('subscribe.create')
            ->with('status', __('subscribe.flash.email_sent'));
    }

    private const DAILY_SMS_BUDGET = 200;
    private const DAILY_EMAIL_BUDGET = 1000;
    private const PER_VALUE_COOLDOWN_SECONDS = 900;     // 15 minutes
    private const PER_SUBSCRIBER_COOLDOWN_SECONDS = 60; // 1 minute

    private function canResendEmail(Subscriber $s): bool
    {
        return ! Cache::has('subscriber_email_cooldown:'.$s->getKey());
    }

    private function markEmailSent(Subscriber $s): void
    {
        Cache::put('subscriber_email_cooldown:'.$s->getKey(), 1, self::PER_SUBSCRIBER_COOLDOWN_SECONDS);
    }

    private function canResendSms(Subscriber $s): bool
    {
        return ! Cache::has('subscriber_sms_cooldown:'.$s->getKey());
    }

    private function markSmsSent(Subscriber $s): void
    {
        Cache::put('subscriber_sms_cooldown:'.$s->getKey(), 1, self::PER_SUBSCRIBER_COOLDOWN_SECONDS);
    }

    private function canSendToEmail(string $email): bool
    {
        return ! Cache::has('email_cooldown:'.sha1(strtolower($email)));
    }

    private function markEmailAddressSent(string $email): void
    {
        Cache::put('email_cooldown:'.sha1(strtolower($email)), 1, self::PER_VALUE_COOLDOWN_SECONDS);
    }

    private function canSendToPhone(string $phone): bool
    {
        return ! Cache::has('phone_cooldown:'.sha1($phone));
    }

    private function markPhoneSent(string $phone): void
    {
        Cache::put('phone_cooldown:'.sha1($phone), 1, self::PER_VALUE_COOLDOWN_SECONDS);
    }

    private function underDailyEmailBudget(): bool
    {
        return (int) Cache::get('daily_email_count:'.now()->format('Y-m-d'), 0) < self::DAILY_EMAIL_BUDGET;
    }

    private function bumpDailyEmailCounter(): void
    {
        Cache::increment('daily_email_count:'.now()->format('Y-m-d'));
        Cache::put('daily_email_count:'.now()->format('Y-m-d'),
            (int) Cache::get('daily_email_count:'.now()->format('Y-m-d'), 1),
            now()->endOfDay()->diffInSeconds(now()) + 60);
    }

    private function underDailySmsBudget(): bool
    {
        return (int) Cache::get('daily_sms_count:'.now()->format('Y-m-d'), 0) < self::DAILY_SMS_BUDGET;
    }

    private function bumpDailySmsCounter(): void
    {
        Cache::increment('daily_sms_count:'.now()->format('Y-m-d'));
        Cache::put('daily_sms_count:'.now()->format('Y-m-d'),
            (int) Cache::get('daily_sms_count:'.now()->format('Y-m-d'), 1),
            now()->endOfDay()->diffInSeconds(now()) + 60);
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
