<x-cachet::cachet :title="__('subscribe.title')">
    <x-cachet::header />

    <main class="es-shell es-shell-narrow">
        <h1 class="es-page-title">{{ __('subscribe.title') }}</h1>
        <p class="es-page-intro">{{ __('subscribe.intro') }}</p>

        @if (session('status'))
            <div class="es-flash es-flash-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="es-flash es-flash-error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="es-form-card">
            <form method="POST" action="{{ route('subscribe.store') }}">
                @csrf

                {{-- Anti-bot honeypot: real users never see/fill this. --}}
                <div aria-hidden="true" style="position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden;">
                    <label>Site web<input type="text" name="website" tabindex="-1" autocomplete="off" value=""></label>
                </div>
                {{-- Anti-bot min-time: bots submit instantly. --}}
                <input type="hidden" name="rendered_at" value="{{ now()->getTimestamp() }}">

                <div class="es-field">
                    <label for="email" class="es-label">{{ __('subscribe.field.email') }}</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" class="es-input" placeholder="vous@exemple.com">
                </div>

                <div class="es-field">
                    <label for="phone_number" class="es-label">{{ __('subscribe.field.phone') }}</label>
                    <input type="tel" name="phone_number" id="phone_number" value="{{ old('phone_number') }}" class="es-input" placeholder="+41791234567">
                    <p class="es-help">{{ __('subscribe.field.phone_help') }}</p>
                </div>

                <fieldset class="es-fieldset">
                    <legend class="es-legend">{{ __('subscribe.field.scope') }}</legend>

                    <label class="es-checkbox">
                        <input type="checkbox" name="global" value="1" {{ old('global', true) ? 'checked' : '' }}>
                        <span>{{ __('subscribe.field.global') }}</span>
                    </label>

                    <details style="margin-top: 16px;">
                        <summary style="cursor: pointer; font-size: 13px; color: var(--es-text-soft);">{{ __('subscribe.field.choose_components') }}</summary>
                        <div style="margin-top: 14px;">
                            @foreach ($componentGroups as $group)
                                @if ($group->components->isNotEmpty())
                                    <div class="es-group-card">
                                        <div class="es-group-head">{{ $group->name }}</div>
                                        <div class="es-group-body">
                                            @foreach ($group->components as $component)
                                                <label class="es-checkbox">
                                                    <input type="checkbox" name="components[]" value="{{ $component->id }}" @checked(in_array($component->id, old('components', []), true))>
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
                                                <input type="checkbox" name="components[]" value="{{ $component->id }}" @checked(in_array($component->id, old('components', []), true))>
                                                <span>{{ $component->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </details>
                </fieldset>

                <div class="es-form-actions">
                    <button type="submit" class="es-btn-primary">{{ __('subscribe.action.submit') }}</button>
                </div>
            </form>
        </div>
    </main>

    @include('subscribe.partials.global-toggle-script')

    <x-cachet::footer />
</x-cachet::cachet>
