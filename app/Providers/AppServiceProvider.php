<?php

/*
 * This file is part of Cachet.
 *
 * (c) Alt Three Services Limited
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Providers;

use App\Contracts\IncidentWriter;
use App\Contracts\SmsSender;
use App\Contracts\UrlShortener;
use App\Listeners\NotifySubscribersOfIncident;
use App\Observers\ScheduleObserver;
use App\Services\Ai\ClaudeWriter;
use App\Services\Sms\SmsEagleClient;
use App\Services\Url\SlinkShortener;
use App\View\Composers\StatusPageComposer;
use Cachet\Events\Incidents\IncidentCreated;
use Cachet\Events\Incidents\IncidentUpdated;
use Cachet\Models\Schedule;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\View\PanelsRenderHook;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SmsSender::class, function ($app): SmsSender {
            $config = $app['config']->get('smseagle');

            return new SmsEagleClient(
                baseUrl: rtrim((string) $config['base_url'], '/'),
                token: $config['token'] ?? null,
                defaultModem: $config['modem'] ?? null,
                timeout: (int) ($config['timeout'] ?? 10),
            );
        });

        $this->app->singleton(UrlShortener::class, function ($app): UrlShortener {
            $config = $app['config']->get('slink');

            return new SlinkShortener(
                baseUrl: rtrim((string) $config['base_url'], '/'),
                apiKey: $config['api_key'] ?? null,
                slugPrefix: trim((string) ($config['slug_prefix'] ?? 'status'), '/'),
                timeout: (int) ($config['timeout'] ?? 5),
            );
        });

        $this->app->singleton(IncidentWriter::class, function ($app): IncidentWriter {
            $config = $app['config']->get('anthropic');

            return new ClaudeWriter(
                apiKey: (string) ($config['api_key'] ?? ''),
                model: (string) ($config['model'] ?? 'claude-sonnet-4-6'),
                baseUrl: rtrim((string) ($config['base_url'] ?? 'https://api.anthropic.com'), '/'),
                version: (string) ($config['version'] ?? '2023-06-01'),
                timeout: (int) ($config['timeout'] ?? 30),
                maxTokens: (int) ($config['max_tokens'] ?? 1024),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootRoute();
        $this->bootNotifications();
        $this->bootAiAssistant();
        $this->bootStatusPage();
        $this->bootFilamentFormDefaults();
    }

    /**
     * Default the Filament "Notifications" toggle to ON when creating a new
     * incident. Operators were silently forgetting to tick it (2/2 cases on
     * our prod) and subscribers got no mail/SMS. Mirrors the default behavior
     * of Atlassian Statuspage; the operator can always untick before saving
     * for internal corrections.
     */
    private function bootFilamentFormDefaults(): void
    {
        Toggle::configureUsing(function (Toggle $toggle): void {
            if ($toggle->getName() === 'notifications') {
                $toggle->default(true);
            }
        });
    }

    private function bootStatusPage(): void
    {
        View::composer('cachet::status-page.index', StatusPageComposer::class);
    }

    private function bootAiAssistant(): void
    {
        Filament::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => auth()->check() && auth()->user()?->isAdmin()
                ? view('filament.ai-assistant')->render()
                : '',
        );
    }

    private function bootNotifications(): void
    {
        Event::listen(IncidentCreated::class, [NotifySubscribersOfIncident::class, 'handle']);
        Event::listen(IncidentUpdated::class, [NotifySubscribersOfIncident::class, 'handle']);

        Schedule::observe(ScheduleObserver::class);
    }

    public function bootRoute(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
