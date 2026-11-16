<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Team Dashboard') — {{ \App\Models\Setting::siteName() }}</title>
    @if (\App\Models\Setting::faviconUrl())
        <link rel="icon" href="{{ \App\Models\Setting::faviconUrl() }}">
    @else
        <link rel="icon" href="{{ asset('favicon.ico') }}">
    @endif
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ file_exists(public_path('assets/css/app.css')) ? filemtime(public_path('assets/css/app.css')) : time() }}">
</head>
<body>
    <div class="app-shell">
        <div id="sidebar-overlay" class="sidebar-overlay" aria-hidden="true"></div>

        <aside id="sidebar" class="sidebar" aria-label="Team Navigation">
            <div class="flex flex-col h-full w-full">
                <div class="sidebar-header">
                    <div class="sidebar-brand">
                        @php $brandLogo = \App\Models\Setting::logoTransparentUrl() ?? \App\Models\Setting::logoUrl(); @endphp
                        @if ($brandLogo)
                            <img src="{{ $brandLogo }}" alt="{{ \App\Models\Setting::siteName() }}" class="sidebar-brand-img">
                        @else
                            <span class="sidebar-brand-icon">
                                {{ strtoupper(substr(\App\Models\Setting::siteName(), 0, 1)) }}
                            </span>
                            <span class="sidebar-brand-text">{{ \App\Models\Setting::siteName() }}</span>
                        @endif
                    </div>
                    <button type="button" id="sidebar-close-btn" class="sidebar-close-btn" aria-label="Close menu">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <nav class="sidebar-nav">
                    <p class="sidebar-section-title">Team Portal</p>
                    @php
                        $menuItems = [
                            ['route' => 'team.dashboard', 'label' => 'Dashboard', 'match' => 'team.dashboard'],
                            ['route' => 'team.orders.create', 'label' => 'Create Order', 'match' => 'team.orders.create'],
                            ['route' => 'team.orders', 'label' => 'Orders', 'match' => 'team.orders*'],
                            ['route' => 'team.customers', 'label' => 'Customers', 'match' => 'team.customers'],
                            ['route' => 'team.payments', 'label' => 'Payments', 'match' => 'team.payments'],
                            ['route' => 'team.subscriptions', 'label' => 'Subscriptions', 'match' => 'team.subscriptions'],
                            ['route' => 'team.members', 'label' => 'Team Members', 'match' => 'team.members'],
                            ['route' => 'team.notifications', 'label' => 'Notifications', 'match' => 'team.notifications'],
                        ];
                    @endphp

                    <div class="sidebar-menu mt-1">
                        @foreach ($menuItems as $item)
                            <a href="{{ route($item['route']) }}"
                               class="sidebar-link {{ request()->routeIs($item['match']) ? 'active' : '' }}">
                                {{ $item['label'] }}
                            </a>
                        @endforeach

                        <div class="sidebar-divider">
                            <p class="sidebar-section-title">Account</p>
                            <a href="{{ route('team.dashboard') }}"
                               class="sidebar-link {{ request()->routeIs('team.dashboard') && request()->routeIs('team.profile*') ? 'active' : '' }}">
                                Profile & Settings
                            </a>
                        </div>
                    </div>
                </nav>
            </div>
        </aside>

        <div class="main-column">
            <header class="page-header">
                <div class="header-left">
                    <button type="button" id="sidebar-toggle" aria-label="Toggle menu" class="sidebar-toggle-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-menu h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-close h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <a href="{{ route('team.dashboard') }}" class="header-logo">
                        @php $headerLogo = \App\Models\Setting::logoTransparentUrl() ?? \App\Models\Setting::logoUrl(); @endphp
                        @if ($headerLogo)
                            <img src="{{ $headerLogo }}" alt="{{ \App\Models\Setting::siteName() }}" class="header-logo-img">
                        @else
                            <span class="header-logo-icon">
                                {{ strtoupper(substr(\App\Models\Setting::siteName(), 0, 1)) }}
                            </span>
                            <span class="header-logo-text hidden sm:inline">{{ \App\Models\Setting::siteName() }}</span>
                        @endif
                    </a>

                    <span class="header-divider hidden sm:inline-block"></span>
                    <span class="header-role-badge">Team</span>
                </div>
                <div class="header-right">
                    <span class="header-username">{{ request()->user()?->team_name ?? request()->user()?->team_code }}</span>
                    <form method="POST" action="{{ route('team.logout') }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="logout-btn">Log out</button>
                    </form>
                </div>
            </header>

            <main class="page-content">
                <div class="page-content-inner">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    <script type="module" src="{{ asset('assets/js/pages/team-dashboard.js') }}"></script>
    @yield('scripts')
    @stack('scripts')
</body>
</html>
