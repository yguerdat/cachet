<link rel="stylesheet" href="{{ asset('vendor/eseances/brand.css') }}?v=7">
<header class="es-header">
    <div class="es-header-inner">
        <a href="{{ url(\Cachet\Cachet::path()) }}" class="es-logo-link" aria-label="eSéances Status">
            <picture>
                <source srcset="{{ asset('vendor/eseances/logo-eseances-full-dark.svg') }}" media="(prefers-color-scheme: dark)" />
                <img src="{{ asset('vendor/eseances/logo-eseances-full.svg') }}" alt="eSéances" class="es-logo-img" />
            </picture>
            <span class="es-logo-tag">Status de l'écosystème eSéances</span>
        </a>
        <a href="{{ route('subscribe.create') }}" class="es-subscribe-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
            <span>S'abonner aux notifications</span>
        </a>
    </div>
</header>
