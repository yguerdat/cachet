<x-cachet::cachet :title="__('subscribe.manage.title')">
    <x-cachet::header />

    <main class="es-shell es-shell-narrow">
        <h1 class="es-page-title">{{ __('subscribe.manage.title') }}</h1>
        <p class="es-page-intro">{{ __('subscribe.manage.intro') }}</p>

        @if (session('status'))
            <div class="es-flash es-flash-success">{{ session('status') }}</div>
        @endif

        <div class="es-info-list">
            @if ($subscriber->email)
                <div class="es-info-item">
                    <div class="es-info-label">{{ __('subscribe.manage.email') }}</div>
                    <div>
                        <span class="es-info-value">{{ $subscriber->email }}</span>
                        @if ($subscriber->verified_at)
                            <span class="es-info-value-meta">✓ {{ __('subscribe.manage.verified') }}</span>
                        @else
                            <span class="es-info-value-meta es-info-value-meta-warn">{{ __('subscribe.manage.not_verified') }}</span>
                        @endif
                    </div>
                </div>
            @endif
            @if ($subscriber->phone_number)
                <div class="es-info-item">
                    <div class="es-info-label">{{ __('subscribe.manage.phone') }}</div>
                    <div>
                        <span class="es-info-value">{{ $subscriber->phone_number }}</span>
                        @if ($subscriber->phone_verified_at)
                            <span class="es-info-value-meta">✓ {{ __('subscribe.manage.verified') }}</span>
                        @else
                            <span class="es-info-value-meta es-info-value-meta-warn">{{ __('subscribe.manage.not_verified') }}</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <div class="es-form-card">
            <form method="POST" action="{{ route('subscribe.manage.update', $subscriber) }}">
                @csrf

                <fieldset class="es-fieldset">
                    <legend class="es-legend">{{ __('subscribe.field.scope') }}</legend>

                    <label class="es-checkbox">
                        <input type="checkbox" name="global" value="1" {{ $subscriber->global ? 'checked' : '' }}>
                        <span>{{ __('subscribe.field.global') }}</span>
                    </label>

                    <div style="margin-top: 14px;">
                        @foreach ($componentGroups as $group)
                            @if ($group->components->isNotEmpty())
                                <div class="es-group-card">
                                    <div class="es-group-head">{{ $group->name }}</div>
                                    <div class="es-group-body">
                                        @foreach ($group->components as $component)
                                            <label class="es-checkbox">
                                                <input type="checkbox" name="components[]" value="{{ $component->id }}" @checked(in_array($component->id, $subscribedComponentIds, true))>
                                                <span>{{ $component->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                        @if ($ungroupedComponents->isNotEmpty())
                            <div class="es-group-card">
                                <div class="es-group-head">{{ __('subscribe.field.other_components') }}</div>
                                <div class="es-group-body">
                                    @foreach ($ungroupedComponents as $component)
                                        <label class="es-checkbox">
                                            <input type="checkbox" name="components[]" value="{{ $component->id }}" @checked(in_array($component->id, $subscribedComponentIds, true))>
                                            <span>{{ $component->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </fieldset>

                <div class="es-form-actions">
                    <button type="submit" class="es-btn-primary">{{ __('subscribe.manage.save') }}</button>
                    <a href="{{ route('subscribe.unsubscribe', $subscriber) }}" class="es-link-danger">{{ __('subscribe.manage.unsubscribe_link') }}</a>
                </div>
            </form>
        </div>
    </main>

    @include('subscribe.partials.global-toggle-script')

    <x-cachet::footer />
</x-cachet::cachet>
