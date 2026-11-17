<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
    <a class="skip-link" href="#main-content">Skip to main content</a>
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
                <nav class="sidebar-nav overflow-y-auto flex-1">
                    @php
                        $sections = [
                            'Overview' => [
                                ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'match' => 'admin.dashboard', 'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>'],
                                ['route' => 'admin.reports.index', 'label' => 'Reports & Analytics', 'match' => 'admin.reports*', 'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>'],
                            ],
                            'Operations' => [
                                ['route' => 'admin.teams', 'label' => 'Teams', 'match' => 'admin.teams*', 'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>'],
                                ['route' => 'admin.orders', 'label' => 'Orders', 'match' => 'admin.orders*', 'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>'],
                                ['route' => 'admin.products', 'label' => 'Products', 'match' => 'admin.products*', 'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/></svg>'],
                                ['route' => 'admin.customers', 'label' => 'Customers (CRM)', 'match' => 'admin.customers*', 'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>'],
                            ],
                            'Staff & Access' => [
                                ['route' => 'admin.employees', 'label' => 'Employees', 'match' => 'admin.employees*', 'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>'],
                                ['route' => 'admin.presets', 'label' => 'Permissions & Presets', 'match' => 'admin.presets*', 'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>'],
                                ['route' => 'admin.admins', 'label' => 'Admins', 'match' => 'admin.admins*', 'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>'],
                            ],
                            'Communications' => [
                                ['route' => 'admin.notifications', 'label' => 'Push Notifications', 'match' => 'admin.notifications*', 'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>'],
                                ['route' => 'admin.email.index', 'label' => 'Email System', 'match' => 'admin.email*', 'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>'],
                            ],
                            'System & Maintenance' => [
                                ['route' => 'admin.settings', 'label' => 'General Settings', 'match' => 'admin.settings*', 'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h2.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l1.48 1.48a1.125 1.125 0 01.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.559.94 1.109v2.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-1.48 1.48a1.125 1.125 0 01-1.45.12l-.737-.528c-.35-.25-.807-.272-1.204-.107-.397.165-.71.505-.78.929l-.15.894c-.09.542-.56.94-1.11.94h-2.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-1.48-1.48a1.125 1.125 0 01-.12-1.45l.528-.737c.25-.35.272-.806.107-1.205-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-2.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 01.12-1.45l1.48-1.48a1.125 1.125 0 011.45-.12l.737.528c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>'],
                                ['route' => 'admin.api-management.index', 'label' => 'API Management', 'match' => 'admin.api-management*', 'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5"/></svg>'],
                                ['route' => 'admin.api-settings.index', 'label' => 'API Integrations', 'match' => 'admin.api-settings*', 'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.25 9.75L16.5 12l-2.25 2.25m-4.5 0L7.5 12l2.25-2.25M6 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z"/></svg>'],
                                ['route' => 'admin.backups.index', 'label' => 'Backup & Recovery', 'match' => 'admin.backups*', 'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/></svg>'],
                                ['route' => 'admin.installer.recovery', 'label' => 'System Recovery', 'match' => 'admin.installer*', 'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'],
                            ],
                        ];
                    @endphp

                    @foreach($sections as $sectionTitle => $items)
                        @php $sectionKey = \Illuminate\Support\Str::slug($sectionTitle, '-'); @endphp
                        <div class="sidebar-section mb-3" data-collapsible data-section-key="{{ $sectionKey }}">
                            <button type="button"
                                    class="sidebar-section-title"
                                    data-section-toggle
                                    aria-expanded="true"
                                    aria-controls="sidebar-section-{{ $sectionKey }}">
                                <span class="sidebar-section-label">{{ $sectionTitle }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                     stroke="currentColor" class="sidebar-section-chevron w-4 h-4" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>
                            <div class="sidebar-section-body" data-section-body>
                                <div id="sidebar-section-{{ $sectionKey }}" class="sidebar-menu space-y-0.5">
                                    @foreach ($items as $item)
                                            <a href="{{ route($item['route']) }}"
                                               @if(request()->routeIs($item['match'])) aria-current="page" @endif
                                               class="sidebar-link flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->routeIs($item['match']) ? 'active bg-neutral-900 text-white' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900' }}">
                                                {!! $item['icon'] !!}
                                                <span>{{ $item['label'] }}</span>
                                            </a>
                                        @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </nav>
            </div>
        </aside>

        {{-- Main Column --}}
        <div class="main-column flex flex-col min-h-screen">
            {{-- Header --}}
            <header class="page-header border-b border-neutral-200 bg-white sticky top-0 z-30 px-4 sm:px-6 py-2.5 flex items-center justify-between shadow-2xs">
                <div class="header-left flex items-center gap-3">
                    <button type="button" id="sidebar-toggle" aria-label="Toggle menu" aria-controls="sidebar" class="sidebar-toggle-btn p-1.5 rounded-lg text-neutral-600 hover:bg-neutral-100">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-menu h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-close h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <a href="{{ route('admin.dashboard') }}" class="header-logo flex items-center gap-2">
                        @php $headerLogo = \App\Models\Setting::logoTransparentUrl() ?? \App\Models\Setting::logoUrl(); @endphp
                        @if ($headerLogo)
                            <img src="{{ $headerLogo }}" alt="{{ \App\Models\Setting::siteName() }}" class="header-logo-img h-6 w-auto">
                        @else
                            <span class="header-logo-icon w-6 h-6 rounded bg-neutral-900 text-white flex items-center justify-center font-bold text-xs">
                                {{ strtoupper(substr(\App\Models\Setting::siteName(), 0, 1)) }}
                            </span>
                            <span class="header-logo-text font-bold text-sm text-neutral-900 hidden sm:inline">{{ \App\Models\Setting::siteName() }}</span>
                        @endif
                    </a>

                    <span class="header-divider h-4 w-px bg-neutral-200 hidden sm:inline-block"></span>
                    <span class="header-role-badge badge badge-neutral text-2xs uppercase tracking-wider font-bold">Super Admin</span>
                </div>

                {{-- Global Search in Topbar --}}
                <div class="header-center hidden md:flex items-center flex-1 max-w-xs mx-4">
                    <form method="GET" action="{{ route('admin.orders') }}" class="w-full relative">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-neutral-400 absolute left-3 top-2.5 pointer-events-none">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                        <input type="text"
                               name="search"
                               placeholder="Search orders, customers..."
                               class="w-full pl-9 pr-3 py-1.5 text-xs bg-neutral-100 border border-transparent rounded-lg focus:bg-white focus:border-neutral-300 focus:outline-none transition-all">
                    </form>
                </div>

                <div class="header-right flex items-center gap-3">
                    {{-- Notifications Link --}}
                    <a href="{{ route('admin.notifications') }}" class="p-2 text-neutral-500 hover:text-neutral-900 hover:bg-neutral-100 rounded-lg relative" title="Notifications">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                        <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-green-500"></span>
                    </a>

                    {{-- Admin Profile Menu --}}
                    <div class="flex items-center gap-2 pl-2 border-l border-neutral-200">
                        <div class="w-7 h-7 rounded-full bg-neutral-900 text-white flex items-center justify-center text-xs font-bold select-none">
                            {{ strtoupper(substr(auth('admin')->user()->username, 0, 1)) }}
                        </div>
                        <span class="header-username text-xs font-medium text-neutral-800 hidden sm:inline">{{ auth('admin')->user()->username }}</span>
                    </div>

                    <form method="POST" action="{{ route('admin.logout') }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="logout-btn btn btn-secondary text-xs px-2.5 py-1" title="Log out of Super Admin">
                            Log out
                        </button>
                    </form>
                </div>
            </header>

            {{-- Main Content --}}
            <main id="main-content" class="page-content flex-1 p-4 sm:p-6 lg:p-8" tabindex="-1">
                <div class="page-content-inner max-w-7xl mx-auto">
                    @yield('content')
                </div>
            </main>

            {{-- Footer --}}
            <footer class="admin-footer border-t border-neutral-200 bg-white py-4 px-6 text-xs text-neutral-500 flex flex-col sm:flex-row items-center justify-between gap-2">
                <div>
                    &copy; {{ date('Y') }} <strong>{{ \App\Models\Setting::siteName() }}</strong>. Enterprise CRM & Lead Management.
                </div>
                <div class="flex items-center gap-4">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-green-500"></span>
                        <span>Systems Operational</span>
                    </span>
                    <span>v1.0 (Laravel 12)</span>
                </div>
            </footer>
        </div>
    </div>

    <script type="module" src="{{ asset('assets/js/pages/admin-dashboard.js') }}"></script>
    @yield('scripts')
    @stack('scripts')
</body>
</html>
