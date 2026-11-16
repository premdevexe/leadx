<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin Dashboard') — {{ \App\Models\Setting::siteName() }}</title>
    @if (\App\Models\Setting::faviconUrl())
        <link rel="icon" href="{{ \App\Models\Setting::faviconUrl() }}">
    @else
        <link rel="icon" href="{{ asset('favicon.ico') }}">
    @endif
    <link rel="manifest" href="{{ url('/manifest.json') }}">
    <meta name="theme-color" content="#171717">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ file_exists(public_path('assets/css/app.css')) ? filemtime(public_path('assets/css/app.css')) : time() }}">
    @include('partials.push-beams')
</head>
<body>
    <div class="app-shell">
        {{-- Mobile Overlay --}}
        <div id="sidebar-overlay" class="sidebar-overlay" aria-hidden="true"></div>

        {{-- Sidebar --}}
        <aside id="sidebar" class="sidebar" aria-label="Admin Navigation">
            <div class="flex flex-col h-full w-full">
                {{-- Sidebar Header --}}
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

                {{-- Sidebar Navigation --}}
                <nav class="sidebar-nav">
                    <p class="sidebar-section-title">Management</p>
                    @php
                        $menuItems = [
                            ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'match' => 'admin.dashboard'],
                            ['route' => 'admin.reports.index', 'label' => 'Reports & Analytics', 'match' => 'admin.reports*'],
                            ['route' => 'admin.teams', 'label' => 'Teams', 'match' => 'admin.teams*'],
                            ['route' => 'admin.orders', 'label' => 'Orders', 'match' => 'admin.orders*'],
                            ['route' => 'admin.products', 'label' => 'Products', 'match' => 'admin.products*'],
                            ['route' => 'admin.customers', 'label' => 'Customers (CRM)', 'match' => 'admin.customers*'],
                            ['route' => 'admin.employees', 'label' => 'Employees', 'match' => 'admin.employees*'],
                            ['route' => 'admin.admins', 'label' => 'Admins', 'match' => 'admin.admins*'],
                            ['route' => 'admin.notifications', 'label' => 'Send Notification', 'match' => 'admin.notifications*'],
                            ['route' => 'admin.email.index', 'label' => 'Email Management', 'match' => 'admin.email*'],
                            ['route' => 'admin.settings', 'label' => 'Settings', 'match' => 'admin.settings*'],
                            ['route' => 'admin.api-settings.index', 'label' => 'API Integrations', 'match' => 'admin.api-settings*'],
                        ];
                    @endphp

                    <div class="sidebar-menu mt-1">
                        @foreach ($menuItems as $item)
                            <a href="{{ route($item['route']) }}"
                               class="sidebar-link {{ request()->routeIs($item['match']) ? 'active' : '' }}">
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </nav>
            </div>
        </aside>

        {{-- Main Column --}}
        <div class="main-column">
            {{-- Header --}}
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

                    <a href="{{ route('admin.dashboard') }}" class="header-logo">
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
                    <span class="header-role-badge">Admin</span>
                </div>
                <div class="header-right">
                    <span class="header-username">{{ auth('admin')->user()->username }}</span>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="logout-btn">Log out</button>
                    </form>
                </div>
            </header>

            {{-- Main Content --}}
            <main class="page-content">
                <div class="page-content-inner">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    <script type="module" src="{{ asset('assets/js/pages/admin-dashboard.js') }}"></script>
    @yield('scripts')
    @stack('scripts')
</body>
</html>
